<?php

namespace App\Support;

use RuntimeException;

/** Appel IA refusé par AiBudget : plafond de dépense atteint. */
final class AiBudgetExceeded extends RuntimeException
{
}
