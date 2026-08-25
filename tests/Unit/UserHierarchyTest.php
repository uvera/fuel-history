<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

/**
 * Pure tests for User::manages() / isAncestorOf() / isRootAdmin() —
 * relations are wired manually via setRelation() rather than persisted,
 * in the same "no DB needed" spirit as AnalyticsServiceTest /
 * FuelStretchServiceTest, since the hierarchy logic itself is pure PHP
 * over the created_by chain.
 */
class UserHierarchyTest extends TestCase
{
    private function makeUser(int $id, string $role, ?User $createdBy = null): User
    {
        $user = new User([
            'name' => "User {$id}",
            'email' => "user{$id}@example.com",
            'role' => $role,
        ]);
        $user->id = $id;
        $user->created_by = $createdBy?->id;
        $user->setRelation('createdBy', $createdBy);

        return $user;
    }

    public function test_root_admin_manages_direct_and_transitive_descendants(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $child = $this->makeUser(2, User::ROLE_ADMIN, $root);
        $grandchild = $this->makeUser(3, User::ROLE_ADMIN, $child);

        $this->assertTrue($root->manages($child));
        $this->assertTrue($root->manages($grandchild));
        $this->assertTrue($child->manages($grandchild));
    }

    public function test_nested_admin_cannot_manage_parent_or_ancestors(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $child = $this->makeUser(2, User::ROLE_ADMIN, $root);
        $grandchild = $this->makeUser(3, User::ROLE_ADMIN, $child);

        $this->assertFalse($child->manages($root));
        $this->assertFalse($grandchild->manages($child));
        $this->assertFalse($grandchild->manages($root));
    }

    public function test_sibling_admins_cannot_manage_each_other(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $siblingA = $this->makeUser(2, User::ROLE_ADMIN, $root);
        $siblingB = $this->makeUser(3, User::ROLE_ADMIN, $root);

        $this->assertFalse($siblingA->manages($siblingB));
        $this->assertFalse($siblingB->manages($siblingA));
    }

    public function test_root_admins_are_never_manageable_even_by_each_other(): void
    {
        $rootA = $this->makeUser(1, User::ROLE_ADMIN);
        $rootB = $this->makeUser(2, User::ROLE_ADMIN);

        $this->assertFalse($rootA->manages($rootB));
        $this->assertFalse($rootB->manages($rootA));
    }

    public function test_root_admin_manages_unowned_regular_user(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $unownedRegular = $this->makeUser(2, User::ROLE_USER);

        $this->assertTrue($root->manages($unownedRegular));
    }

    public function test_nested_admin_cannot_manage_unowned_regular_user(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $nestedAdmin = $this->makeUser(2, User::ROLE_ADMIN, $root);
        $unownedRegular = $this->makeUser(3, User::ROLE_USER);

        $this->assertFalse($nestedAdmin->manages($unownedRegular));
    }

    public function test_admin_cannot_manage_self(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);

        $this->assertFalse($root->manages($root));
    }

    public function test_regular_user_cannot_manage_anyone(): void
    {
        $root = $this->makeUser(1, User::ROLE_ADMIN);
        $regular = $this->makeUser(2, User::ROLE_USER, $root);
        $other = $this->makeUser(3, User::ROLE_USER, $root);

        $this->assertFalse($regular->manages($other));
        $this->assertFalse($regular->manages($root));
    }

    public function test_ancestor_chain_walk_guards_against_cycles(): void
    {
        // Data anomaly: p1 -> p3 -> p2 -> p1 -> ... (a ring, no root).
        // A user outside the ring must never infinite-loop walking it.
        $p1 = $this->makeUser(1, User::ROLE_ADMIN);
        $p2 = $this->makeUser(2, User::ROLE_ADMIN);
        $p3 = $this->makeUser(3, User::ROLE_ADMIN);

        $p1->created_by = $p3->id;
        $p1->setRelation('createdBy', $p3);
        $p2->created_by = $p1->id;
        $p2->setRelation('createdBy', $p1);
        $p3->created_by = $p2->id;
        $p3->setRelation('createdBy', $p2);

        $outsider = $this->makeUser(99, User::ROLE_ADMIN);

        $this->assertFalse($outsider->isAncestorOf($p1));
        $this->assertFalse($outsider->manages($p1));
    }
}
