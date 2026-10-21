<?php

declare(strict_types=1);

namespace Ahmdrv\Navigation\Support;

use Ahmdrv\Navigation\Contracts\NavigationAuthorization;
use Ahmdrv\Navigation\Contracts\NavigationContext;
use Ahmdrv\Navigation\Contracts\RolePermissionAdapter;
use Ahmdrv\Navigation\NavigationNode;
use Illuminate\Contracts\Auth\Access\Gate;

final readonly class DefaultNavigationAuthorization implements NavigationAuthorization
{
    public function __construct(
        private Gate $gate,
        private RolePermissionAdapter $adapter,
        private CallbackEvaluator $callbacks,
    ) {
    }

    public function allows(NavigationNode $node, NavigationContext $context): bool
    {
        $user = $context->user();
        $arguments = [$user, $node, $context];

        foreach ($node->visibilityRules() as $rule) {
            if (! $this->callbacks->evaluate($rule, $arguments)) {
                return false;
            }
        }

        foreach ($node->hiddenRules() as $rule) {
            if ($this->callbacks->evaluate($rule, $arguments)) {
                return false;
            }
        }

        foreach ($node->roleRules() as $roles) {
            if ($roles === [] || $user === null || ! $this->adapter->hasAnyRole($user, $roles)) {
                return false;
            }
        }

        foreach ($node->permissionRules() as $permissions) {
            if ($permissions === [] || $user === null || ! $this->adapter->hasAnyPermission($user, $permissions)) {
                return false;
            }
        }

        foreach ($node->gateRules() as [$ability, $gateArguments]) {
            if (! $this->gate->forUser($user)->allows($ability, $gateArguments)) {
                return false;
            }
        }

        foreach ($node->authorizationRules() as $rule) {
            if (! $this->callbacks->evaluate($rule, $arguments)) {
                return false;
            }
        }

        return true;
    }
}
