<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (BSD). If you
 * did not receive this file, see http://www.horde.org/licenses/bsd.
 */

namespace Horde\Rdo;

/**
 * Visitor interface for backend-specific Criterion translation.
 *
 * Each backend (SQL, LDAP, Memory, ...) implements this interface.
 * The visitor walks the Criterion tree and produces backend-native
 * query representations.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/bsd BSD
 * @package  Rdo
 */
interface CriterionVisitor
{
    public function visitField(FieldCriterion $criterion): mixed;

    public function visitComposite(CompositeCriterion $criterion): mixed;

    public function visitNot(NotCriterion $criterion): mixed;

    public function visitRelation(RelationCriterion $criterion): mixed;

    public function visitRaw(RawCriterion $criterion): mixed;
}
