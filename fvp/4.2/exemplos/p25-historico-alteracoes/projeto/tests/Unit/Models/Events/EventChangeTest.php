<?php

namespace Tests\Unit\Models\Events;

use App\Models\Event;
use App\Models\EventChange;
use App\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Histórico de alterações do Evento: Event::saveWithHistory(), Event::changedFields(),
 * Event::history() e o model EventChange.
 */
class EventChangeTest extends TestCase
{
    private User $admin;
    private User $editor;

    public function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('Administrador', 'admin@example.com');
        $this->editor = $this->createUser('Editora', 'editora@example.com');
    }

    private function createUser(string $name, string $email): User
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'password' => '123456',
            'password_confirmation' => '123456',
            'role' => 'admin',
        ]);
        $user->save();

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createEvent(array $overrides = []): Event
    {
        $event = new Event(array_merge([
            'user_id' => $this->admin->id,
            'title' => 'Semana Acadêmica',
            'description' => 'Palestras e minicursos.',
            'kind' => 'lecture',
            'modality' => 'in_person',
            'audience' => 'students,professors',
            'starts_on' => date('Y-m-d', strtotime('+30 days')),
            'location' => 'Auditório Central',
            'capacity' => 10,
        ], $overrides));
        $this->assertTrue($event->save());

        return $event;
    }

    private function loaded(Event $event): Event
    {
        $found = Event::findById($event->id);
        $this->assertInstanceOf(Event::class, $found);

        return $found;
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createChange(Event $event, array $overrides = []): EventChange
    {
        $change = new EventChange(array_merge([
            'event_id' => $event->id,
            'user_id' => $this->editor->id,
            'field' => 'title',
            'old_value' => 'A',
            'new_value' => 'B',
        ], $overrides));
        $this->assertTrue($change->save());

        return $change;
    }

    /* ------------------- Só os campos alterados ------------------- */

    public function test_save_with_history_should_record_only_the_changed_fields(): void
    {
        $event = $this->loaded($this->createEvent());

        $event->title = 'Semana Acadêmica 2027';
        $event->capacity = '25';

        $this->assertTrue($event->saveWithHistory($this->editor->id));

        $history = $event->history();
        $this->assertCount(2, $history);

        $byField = [];
        foreach ($history as $change) {
            $byField[$change->field] = $change;
        }
        ksort($byField);
        $this->assertSame(['capacity', 'title'], array_keys($byField));

        $this->assertSame('Semana Acadêmica', $byField['title']->old_value);
        $this->assertSame('Semana Acadêmica 2027', $byField['title']->new_value);
        $this->assertSame('10', $byField['capacity']->old_value);
        $this->assertSame('25', $byField['capacity']->new_value);

        foreach ($history as $change) {
            $this->assertEquals($this->editor->id, $change->user_id);
            $this->assertEquals($event->id, $change->event_id);
        }

        $this->assertSame('Semana Acadêmica 2027', $this->loaded($event)->title);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function changedFieldProvider(): array
    {
        return [
            'title' => ['title', 'Outro título'],
            'description' => ['description', 'Outra descrição.'],
            'kind' => ['kind', 'workshop'],
            'modality' => ['modality', 'online'],
            'audience' => ['audience', 'students'],
            'starts_on' => ['starts_on', date('Y-m-d', strtotime('+31 days'))],
            'location' => ['location', 'Bloco B'],
            'capacity' => ['capacity', '11'],
        ];
    }

    #[DataProvider('changedFieldProvider')]
    public function test_save_with_history_should_record_one_row_for_each_field(string $field, string $value): void
    {
        $event = $this->loaded($this->createEvent());

        $event->$field = $value;

        $this->assertTrue($event->saveWithHistory($this->editor->id));
        $history = $event->history();
        $this->assertCount(1, $history);
        $this->assertSame($field, $history[0]->field);
        $this->assertSame($value, $history[0]->new_value);
    }

    /* ------------------- Nenhuma linha quando nada muda ------------------- */

    public function test_save_with_history_should_record_nothing_without_changes(): void
    {
        $event = $this->loaded($this->createEvent());

        $this->assertTrue($event->saveWithHistory($this->editor->id));

        $this->assertSame([], $event->history());
    }

    public function test_same_values_as_the_form_sends_are_not_changes(): void
    {
        $event = $this->loaded($this->createEvent(['description' => "Linha 1\nLinha 2"]));

        // O formulário entrega tudo como string, o SET na ordem marcada e o textarea com \r\n.
        $event->capacity = '10';
        $event->setAudienceList(['professors', 'students']);
        $event->description = "Linha 1\r\nLinha 2";

        $this->assertSame([], $event->changedFields());
        $this->assertTrue($event->saveWithHistory($this->editor->id));
        $this->assertSame([], $event->history());
    }

    public function test_invalid_data_should_not_save_nor_record_history(): void
    {
        $event = $this->loaded($this->createEvent());

        $event->title = 'Mudou';
        $event->capacity = '0';

        $this->assertFalse($event->saveWithHistory($this->editor->id));
        $this->assertSame([], $event->history());
        $this->assertSame('Semana Acadêmica', $this->loaded($event)->title);
    }

    public function test_without_editor_should_not_save_nor_record_history(): void
    {
        $event = $this->loaded($this->createEvent());

        $event->title = 'Mudou';

        $this->assertFalse($event->saveWithHistory(null));
        $this->assertNotNull($event->errors('base'));
        $this->assertSame([], $event->history());
        $this->assertSame('Semana Acadêmica', $this->loaded($event)->title);
    }

    public function test_new_record_has_no_changed_fields_nor_history(): void
    {
        $event = new Event(['title' => 'Novo']);

        $this->assertSame([], $event->changedFields());
        $this->assertSame([], $event->history());
    }

    /* ------------------- Compara com o banco, não com a memória ------------------- */

    public function test_changes_are_compared_with_the_stored_values(): void
    {
        $event = $this->loaded($this->createEvent(['title' => 'Antes']));

        // Outra edição grava "No banco" depois que $event foi carregado.
        $other = $this->loaded($event);
        $other->update(['title' => 'No banco']);

        $event->title = 'Depois';
        $this->assertSame(['title' => ['No banco', 'Depois']], $event->changedFields());

        // Voltar ao valor que já está no banco não é alteração, mesmo diferente do carregado.
        $event->title = 'No banco';
        $this->assertSame([], $event->changedFields());
    }

    /* ------------------- CASCADE ao remover o Evento ------------------- */

    public function test_removing_the_event_removes_its_history(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent(['title' => 'Outro Evento']);
        $this->createChange($event);
        $this->createChange($event, ['field' => 'location']);
        $kept = $this->createChange($other);

        $this->assertTrue($event->safeDestroy());

        $this->assertSame([], EventChange::where(['event_id' => $event->id]));
        $remaining = EventChange::all();
        $this->assertCount(1, $remaining);
        $this->assertSame($kept->id, $remaining[0]->id);
    }

    /* ------------------- Ordem ------------------- */

    public function test_history_should_be_newest_first_with_id_as_tie_breaker(): void
    {
        $event = $this->createEvent();
        $other = $this->createEvent(['title' => 'Outro Evento']);

        $oldest = $this->createChange($event, ['created_at' => '2026-01-10 08:00:00']);
        $sameSecondA = $this->createChange($event, ['field' => 'location', 'created_at' => '2026-03-01 10:00:00']);
        $sameSecondB = $this->createChange($event, ['field' => 'capacity', 'created_at' => '2026-03-01 10:00:00']);
        $middle = $this->createChange($event, ['created_at' => '2026-02-15 09:30:00']);
        $this->createChange($other, ['created_at' => '2026-04-01 00:00:00']);

        $ids = array_map(fn(EventChange $change) => $change->id, $event->history());

        $this->assertSame([$sameSecondB->id, $sameSecondA->id, $middle->id, $oldest->id], $ids);
    }

    /* ------------------- EventChange ------------------- */

    public function test_event_change_requires_event_user_and_a_known_field(): void
    {
        $change = new EventChange();
        $this->assertFalse($change->isValid());
        $this->assertSame('não pode ser vazio!', $change->errors('event_id'));
        $this->assertSame('não pode ser vazio!', $change->errors('user_id'));
        $this->assertSame('não pode ser vazio!', $change->errors('field'));

        $change->field = 'password';
        $change->isValid();
        $this->assertSame('não é uma opção válida!', $change->errors('field'));
    }

    public function test_event_change_fills_created_at_when_missing(): void
    {
        $change = new EventChange(['field' => 'title']);

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $change->created_at);
    }

    public function test_event_change_labels_show_what_the_user_sees(): void
    {
        $event = $this->createEvent();

        $kind = $this->createChange($event, ['field' => 'kind', 'old_value' => 'lecture', 'new_value' => 'workshop']);
        $audience = $this->createChange($event, [
            'field' => 'audience',
            'old_value' => 'students,professors',
            'new_value' => 'community',
        ]);
        $date = $this->createChange($event, [
            'field' => 'starts_on',
            'old_value' => '2027-03-01',
            'new_value' => '2027-03-08',
        ]);

        $this->assertSame('Tipo', $kind->fieldLabel());
        $this->assertSame('Palestra', $kind->oldValueLabel());
        $this->assertSame('Oficina', $kind->newValueLabel());
        $this->assertSame('Público-alvo', $audience->fieldLabel());
        $this->assertSame('Estudantes, Professores', $audience->oldValueLabel());
        $this->assertSame('Comunidade externa', $audience->newValueLabel());
        $this->assertSame('Data', $date->fieldLabel());
        $this->assertSame('01/03/2027', $date->oldValueLabel());
        $this->assertSame('08/03/2027', $date->newValueLabel());
        $this->assertSame('Editora', $kind->userName());
    }
}
