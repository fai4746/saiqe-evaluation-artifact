<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm A — MOD-07 Notifications.
 * Source: SPEC-functional.md FR-07.1 .. FR-07.3.
 */
final class Mod07NotificationsArmATest extends TestCase {

    private array $fx = [];

    protected function setUp(): void { TestSupport::resetDefects(); }

    protected function tearDown(): void {
        foreach ($this->fx as $ids) TestSupport::dropApplication($ids);
        $this->fx = [];
    }

    private function user(): array {
        $ids = TestSupport::makeApplication();
        $this->fx[] = $ids;
        return $ids;
    }

    /** FR-07.1 */
    public function testNotificationReachesItsRecipient(): void {
        $a = $this->user();
        $m = new NotificationModel();
        $m->create($a['user_id'], 'Status Changed', 'Your application moved to Officer Review.');
        $this->assertCount(1, $m->getByUser($a['user_id']));
    }

    /** FR-07.2 */
    public function testRecipientCanMarkTheirOwnNotificationRead(): void {
        $a = $this->user();
        $m = new NotificationModel();
        $id = $m->create($a['user_id'], 'Status Changed', 'Update.');
        $m->markRead($id, $a['user_id']);
        $this->assertSame(0, $m->countUnread($a['user_id']));
    }

    /** FR-07.1 */
    public function testUnreadCountTracksCreatedNotifications(): void {
        $a = $this->user();
        $m = new NotificationModel();
        $m->create($a['user_id'], 'A', 'one');
        $m->create($a['user_id'], 'B', 'two');
        $this->assertSame(2, $m->countUnread($a['user_id']));
    }

    /** FR-07.3 */
    public function testMessageIsRenderedForDisplay(): void {
        $this->assertSame('Your case is ready.', NotificationModel::renderMessage('Your case is ready.'));
    }
}
