<?php

declare(strict_types=1);

namespace Toropyga\DB;

/**
 * Temporary backwards-compatible alias for the v2.x PDO adapter name.
 *
 * @deprecated Use PDOLIB instead. This wrapper is kept for migration support.
 */
class PDO_LIB extends PDOLIB
{
}
