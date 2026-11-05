<?php

namespace Tests\Integration\Database;

use App\Models\Enrollment;
use App\Models\Event;
use App\Models\User;
use Core\Constants\Constants;
use Tests\TestCase;

/**
 * Roda o populate de verdade (outro processo PHP, no banco de teste: o phpunit.xml define
 * DB_DATABASE=eventos_test com putenv, e o processo filho herda) e confere o que ele criou.
 * Pega Evento inválido no populate, que só apareceria no `./run db:populate`.
 */
class PopulateTest extends TestCase
{
    /** @var array<int, string> */
    private array $output = [];

    public function setUp(): void
    {
        parent::setUp();

        $script = Constants::rootPath()->join('database/Populate/populate.php');
        exec(PHP_BINARY . ' ' . escapeshellarg($script) . ' 2>&1', $this->output, $exitCode);
        $this->assertSame(0, $exitCode, implode("\n", $this->output));
    }

    public function test_populate_creates_two_admins_and_four_participants(): void
    {
        $admin = User::findByEmail('admin@example.com');
        $admin2 = User::findByEmail('admin2@example.com');

        $this->assertNotNull($admin);
        $this->assertNotNull($admin2);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin2->isAdmin());
        $this->assertTrue($admin2->authenticate('123456'));
        $this->assertCount(6, User::all());
        $this->assertCount(2, User::where(['role' => 'admin']));
    }

    public function test_populate_creates_thirty_events_split_between_the_admins(): void
    {
        $admin = User::findByEmail('admin@example.com');
        $admin2 = User::findByEmail('admin2@example.com');
        $this->assertNotNull($admin);
        $this->assertNotNull($admin2);

        $this->assertCount(30, Event::all());
        $this->assertCount(16, Event::where(['user_id' => $admin->id]));
        $this->assertCount(14, Event::where(['user_id' => $admin2->id]));
        $this->assertNotNull(Event::findBy(['title' => 'Evento 17']));
        $this->assertNotNull(Event::findBy(['title' => 'Evento 30']));
        $this->assertSame(6, Event::paginate(1, 5)->totalOfPages());
    }

    public function test_new_events_come_after_the_fixed_ones_and_are_in_the_future(): void
    {
        $events = Event::all();
        $previous = '';
        foreach ($events as $event) {
            $this->assertGreaterThanOrEqual($previous, $event->starts_on);
            $this->assertGreaterThan(date('Y-m-d'), $event->starts_on);
            $previous = $event->starts_on;
        }
    }

    public function test_populate_keeps_the_enrollments_and_the_full_event(): void
    {
        $this->assertCount(14, Enrollment::all());

        $first = Event::all()[0];
        $this->assertSame(0, $first->availableSpots());
        $this->assertSame(3, $first->enrollmentsCount());
    }

    public function test_populate_reports_what_it_created(): void
    {
        $text = implode("\n", $this->output);

        $this->assertStringContainsString('admin@example.com e admin2@example.com (admin)', $text);
        $this->assertStringContainsString('16 Eventos criados pelo Administrador', $text);
        $this->assertStringContainsString('14 Eventos criados pela Administradora Dois', $text);
        $this->assertStringContainsString('14 Inscrições criadas em 6 Eventos', $text);
    }
}
