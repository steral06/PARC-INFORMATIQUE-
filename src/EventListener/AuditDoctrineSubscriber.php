<?php

namespace App\EventListener;

use App\Entity\AuditLog;
use App\Service\AuditLogger;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuditDoctrineSubscriber implements EventSubscriber
{
    /** @var array<int, array{entity: object, metadata: ClassMetadata, route: string}> */
    private array $pendingInserts = [];
    /** @var array<int, array{entity: object, changes: array<string, array{old: string, new: string}>, metadata: ClassMetadata, route: string}> */
    private array $pendingUpdates = [];
    /** @var array<int, array{entity: object, metadata: ClassMetadata, label: string, route: string}> */
    private array $pendingRemoves = [];

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function getSubscribedEvents(): array
    {
        return [
            Events::prePersist,
            Events::preUpdate,
            Events::preRemove,
            Events::postFlush,
        ];
    }

    public function prePersist(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        $metadata = $args->getObjectManager()->getClassMetadata(get_class($entity));
        $route = $this->getCurrentRoute();
        $this->pendingInserts[] = ['entity' => $entity, 'metadata' => $metadata, 'route' => $route];
    }

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        $changes = [];
        foreach ($args->getEntityChangeSet() as $field => $values) {
            // Ignorer les timestamps d'activité / heartbeat qui ne constituent pas des actions utilisateur
            if ($entity instanceof \App\Entity\PosteAutorise && ($field === 'dateDerniereActivite' || $field === 'dateDerniereConnexion')) {
                continue;
            }

            [$oldValue, $newValue] = $values;
            // Never log password or PIN hashes
            if (stripos($field, 'password') !== false || stripos($field, 'pin') !== false) {
                $changes[$field] = ['old' => '(modifié)', 'new' => '(modifié)'];
            } else {
                $changes[$field] = ['old' => $this->normalizeValue($oldValue), 'new' => $this->normalizeValue($newValue)];
            }
        }

        if (!empty($changes)) {
            $metadata = $args->getObjectManager()->getClassMetadata(get_class($entity));
            $route = $this->getCurrentRoute();
            $this->pendingUpdates[] = [
                'entity' => $entity,
                'changes' => $changes,
                'metadata' => $metadata,
                'route' => $route,
            ];
        }
    }

    public function preRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if ($this->shouldSkipEntity($entity)) {
            return;
        }

        $metadata = $args->getObjectManager()->getClassMetadata(get_class($entity));
        $label = $this->getEntityLabel($entity, $metadata);
        $route = $this->getCurrentRoute();
        $this->pendingRemoves[] = [
            'entity' => $entity,
            'metadata' => $metadata,
            'label' => $label,
            'route' => $route,
        ];
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $ip = $request?->getClientIp() ?? '127.0.0.1';
        $username = $this->getCurrentUsername() ?? 'Système';

        foreach ($this->pendingInserts as $item) {
            $entity = $item['entity'];
            $metadata = $item['metadata'];
            $route = $item['route'] ?? '';
            $action = $this->getActionForEntity($entity, 'create');
            $level = $this->getLevelForEntity($entity, 'create');
            $description = $this->generateDescription($username, $action, $entity, $metadata, $route, 'create');
            
            $this->auditLogger->log(
                action: $action,
                level: $level,
                status: 'SUCCES',
                username: $username,
                ipAddress: $ip,
                entityType: $this->getEntityTypeLabel($entity),
                entityId: (string) $this->getEntityIdentifier($entity, $metadata),
                description: $description,
                details: null,
            );
        }

        foreach ($this->pendingUpdates as $item) {
            $entity = $item['entity'];
            $metadata = $item['metadata'];
            $route = $item['route'] ?? '';
            $action = $this->getActionForEntity($entity, 'update');
            $level = $this->getLevelForEntity($entity, 'update');
            $description = $this->generateDescription($username, $action, $entity, $metadata, $route, 'update');
            
            $this->auditLogger->log(
                action: $action,
                level: $level,
                status: 'SUCCES',
                username: $username,
                ipAddress: $ip,
                entityType: $this->getEntityTypeLabel($entity),
                entityId: (string) $this->getEntityIdentifier($entity, $metadata),
                description: $description,
                details: $item['changes'],
            );
        }

        foreach ($this->pendingRemoves as $item) {
            $entity = $item['entity'];
            $metadata = $item['metadata'];
            $route = $item['route'] ?? '';
            $action = $this->getActionForEntity($entity, 'delete');
            $level = $this->getLevelForEntity($entity, 'delete');
            $description = $this->generateDescription($username, $action, $entity, $metadata, $route, 'delete');
            
            $this->auditLogger->log(
                action: $action,
                level: $level,
                status: 'SUCCES',
                username: $username,
                ipAddress: $ip,
                entityType: $this->getEntityTypeLabel($entity),
                entityId: (string) $this->getEntityIdentifier($entity, $metadata),
                description: $description,
                details: null,
            );
        }

        $this->pendingInserts = [];
        $this->pendingUpdates = [];
        $this->pendingRemoves = [];
    }

    private function shouldSkipEntity(object $entity): bool
    {
        // Never audit AuditLog itself
        if ($entity instanceof AuditLog) {
            return true;
        }

        return false;
    }

    private function getCurrentUsername(): ?string
    {
        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return null;
        }
        $user = $token->getUser();
        return method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null;
    }

    private function getCurrentRoute(): string
    {
        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return '';
        }
        
        // Try to get the route name from the request attributes
        $route = $request->attributes->get('_route');
        if ($route) {
            return (string) $route;
        }
        
        // Fallback: try to get from the request URI
        return $request->getRequestUri() ?? '';
    }

    private function generateDescription(string $username, string $action, object $entity, ClassMetadata $metadata, string $route, string $operation): string
    {
        $entityType = $this->getEntityTypeLabel($entity);
        $entityLabel = $this->getEntityLabel($entity, $metadata);
        $operationVerb = $this->getOperationVerb($operation);
        
        // Map routes to descriptive actions
        $routeDescriptions = [
            // Equipement
            'app_equipement_new' => 'a créé l\'équipement',
            'app_equipement_edit' => 'a modifié l\'équipement',
            'app_equipement_delete' => 'a supprimé l\'équipement',
            'app_equipement_attribuer_rapide' => 'a attribué l\'équipement à un bénéficiaire',
            
            // Composant
            'app_composant_new' => 'a ajouté le composant',
            'app_composant_edit' => 'a modifié le composant',
            'app_composant_delete' => 'a retiré le composant',
            
            // Attribution
            'app_attribution_new' => 'a créé l\'attribution',
            'app_attribution_edit' => 'a modifié l\'attribution',
            'app_attribution_delete' => 'a supprimé l\'attribution',
            'app_attribution_restituer' => 'a restitué le matériel',
            
            // CategorieEquipement
            'app_categorie_new' => 'a créé la catégorie',
            'app_categorie_edit' => 'a modifié la catégorie',
            'app_categorie_delete' => 'a supprimé la catégorie',
            
            // Lieu
            'app_lieu_new' => 'a créé le lieu',
            'app_lieu_edit' => 'a modifié le lieu',
            'app_lieu_delete' => 'a supprimé le lieu',
            
            // Maintenance
            'app_maintenance_new' => 'a créé le ticket de maintenance',
            'app_maintenance_edit' => 'a modifié le ticket de maintenance',
            'app_maintenance_delete' => 'a supprimé le ticket de maintenance',
            'app_maintenance_cloturer' => 'a clôturé le ticket de maintenance',
            'app_maintenance_reouvrir' => 'a rouvert le ticket de maintenance',
            
            // PosteAutorise
            'app_postes_new' => 'a ajouté le poste autorisé',
            'app_postes_toggle' => 'a modifié l\'état du poste autorisé',
            'app_postes_delete' => 'a supprimé le poste autorisé',
            
            // User
            'app_user_new' => 'a créé l\'utilisateur',
            'app_user_edit' => 'a modifié l\'utilisateur',
            'app_user_delete' => 'a supprimé l\'utilisateur',
            'app_user_reset_password' => 'a réinitialisé le mot de passe de l\'utilisateur',
            'app_user_reset_pin' => 'a réinitialisé le code PIN 2FA de l\'utilisateur',
            'app_profile' => 'a modifié son profil',
            'app_password_change' => 'a changé son mot de passe',
            
            // Rapport
            'app_rapport_export' => 'a exporté un rapport',
        ];
        
        $description = $routeDescriptions[$route] ?? sprintf('a %s %s', $operationVerb, mb_strtolower($entityType));
        
        return sprintf('L\'utilisateur %s %s "%s"', $username, $description, $entityLabel);
    }

    private function getEntityTypeLabel(object $entity): string
    {
        $class = get_class($entity);
        $shortClass = basename(str_replace('\\', '/', $class));
        
        // Map entity class names to French labels
        $labels = [
            'Equipement' => 'Équipement',
            'Composant' => 'Composant',
            'Attribution' => 'Attribution',
            'CategorieEquipement' => 'Catégorie',
            'Lieu' => 'Lieu',
            'TicketMaintenance' => 'Ticket de maintenance',
            'PosteAutorise' => 'Poste autorisé',
            'User' => 'Utilisateur',
        ];

        return $labels[$shortClass] ?? $shortClass;
    }

    private function isConfigurationEntity(object $entity): bool
    {
        $class = get_class($entity);
        $shortClass = basename(str_replace('\\', '/', $class));
        return in_array($shortClass, ['PosteAutorise', 'User'], true);
    }

    private function getActionForEntity(object $entity, string $operation): string
    {
        if ($this->isConfigurationEntity($entity)) {
            return 'CONFIGURATION';
        }
        return match ($operation) {
            'create' => 'CREATION',
            'update' => 'MODIFICATION',
            'delete' => 'SUPPRESSION',
            default => 'MODIFICATION',
        };
    }

    private function getLevelForEntity(object $entity, string $operation): string
    {
        if ($this->isConfigurationEntity($entity)) {
            return 'WARNING';
        }
        return match ($operation) {
            'delete' => 'WARNING',
            default => 'INFO',
        };
    }

    private function getOperationVerb(string $operation): string
    {
        return match ($operation) {
            'create' => 'créé',
            'update' => 'modifié',
            'delete' => 'supprimé',
            default => 'modifié',
        };
    }

    private function getEntityIdentifier(object $entity, ClassMetadata $metadata): ?int
    {
        $idValues = $metadata->getIdentifierValues($entity);
        // Return first identifier value, but ensure it's not an array (composite keys)
        if (!empty($idValues)) {
            $first = reset($idValues);
            return is_int($first) ? $first : null;
        }
        return null;
    }

    private function getEntityLabel(object $entity, ClassMetadata $metadata): string
    {
        // Try to get a meaningful label for the entity
        if (method_exists($entity, '__toString')) {
            return (string) $entity;
        }

        // Try common identifier fields
        $fields = ['nom', 'nomCat', 'nomLieu', 'numeroSerie', 'tagInventaire', 'adresseIp', 'username', 'adresseIp'];
        foreach ($fields as $field) {
            $getter = 'get' . ucfirst($field);
            if (method_exists($entity, $getter)) {
                $value = $entity->$getter();
                if ($value !== null && $value !== '') {
                    return (string) $value;
                }
            }
        }

        // Fallback to ID
        $id = $this->getEntityIdentifier($entity, $metadata);
        return $id ? "#{$id}" : 'N/A';
    }

    private function normalizeValue(mixed $value): string
    {
        if ($value === null) {
            return '(null)';
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string) $value;
    }
}