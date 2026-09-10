<?php

declare(strict_types=1);

namespace App\Domain\Sync\Contracts;

/**
 * Marker for tenant models that must never enter the desktop outbox.
 *
 * Website content (blog posts, static pages) is authored and served from the
 * cloud only — it is not part of the offline desktop dataset, and the server
 * push applier rejects any table outside TenantSnapshotService::TABLES_IN_ORDER.
 * Enqueueing these rows would only pile up permanently-failed outbox entries,
 * so the observer skips models implementing this interface.
 */
interface ExcludedFromDesktopSync {}
