<?php
/**
 * API entry point: /api/v1/<route>  (or /api/index.php?route=<route> without rewrite)
 */

declare(strict_types=1);

require dirname(__DIR__) . '/core/bootstrap.php';

Rishe_Api::handle();
