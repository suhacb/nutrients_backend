<?php

namespace Tests\Unit\Enums;

use App\Enums\SyncStatus;
use PHPUnit\Framework\TestCase;

class SyncStatusTest extends TestCase
{
    public function test_pending_case_has_correct_value(): void
    {
        $this->assertSame('pending', SyncStatus::Pending->value);
    }

    public function test_synced_case_has_correct_value(): void
    {
        $this->assertSame('synced', SyncStatus::Synced->value);
    }

    public function test_failed_case_has_correct_value(): void
    {
        $this->assertSame('failed', SyncStatus::Failed->value);
    }

    public function test_can_be_hydrated_from_string(): void
    {
        $this->assertSame(SyncStatus::Pending, SyncStatus::from('pending'));
        $this->assertSame(SyncStatus::Synced, SyncStatus::from('synced'));
        $this->assertSame(SyncStatus::Failed, SyncStatus::from('failed'));
    }
}
