<?php

declare(strict_types=1);

namespace WHW\Domain\Rules;

use LogicException;
use WHW\Domain\HolidayStatus;

/**
 * Fixed-order Chain of Responsibility:
 * DailyOverrideRule -> MonthlyHolidayRule -> FridayRule -> DefaultRule.
 */
final readonly class Chain
{
    /** @var list<Rule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            new DailyOverrideRule(),
            new MonthlyHolidayRule(),
            new FridayRule(),
            new DefaultRule(),
        ];
    }

    public function resolve(Context $context): HolidayStatus
    {
        foreach ($this->rules as $rule) {
            $status = $rule->resolve($context);

            if (null !== $status) {
                return $status;
            }
        }

        // Unreachable: DefaultRule always resolves. Fails loudly rather
        // than silently defaulting, in case the chain is ever reordered.
        throw new LogicException('Holiday rule chain produced no decision.');
    }
}
