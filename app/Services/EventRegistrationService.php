<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Support;
use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use PDO;
use RuntimeException;

final class EventRegistrationService
{
    public function __construct(private PDO $db, private array $config, private ?SchemaService $schema = null)
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS event_pages (
            project_uid TEXT PRIMARY KEY REFERENCES projects(uid) ON DELETE CASCADE,
            enabled INTEGER NOT NULL DEFAULT 0, title TEXT NOT NULL DEFAULT '',
            introduction TEXT NOT NULL DEFAULT '', color TEXT NOT NULL DEFAULT '#2563eb',
            updated_at TEXT NOT NULL
        )");
        $this->db->exec("CREATE TABLE IF NOT EXISTS events (
            uid TEXT PRIMARY KEY, project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
            title TEXT NOT NULL, description TEXT NOT NULL DEFAULT '', speaker TEXT NOT NULL DEFAULT '',
            event_date TEXT NOT NULL, event_time TEXT NOT NULL, venue TEXT NOT NULL,
            room TEXT NOT NULL DEFAULT '', capacity INTEGER, published INTEGER NOT NULL DEFAULT 0,
            registration_open INTEGER NOT NULL DEFAULT 1, archived INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )");
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_events_project ON events(project_uid, event_date, event_time)');
        $this->db->exec("CREATE TABLE IF NOT EXISTS event_participants (
            uid TEXT PRIMARY KEY, project_uid TEXT NOT NULL REFERENCES projects(uid) ON DELETE CASCADE,
            event_uid TEXT NOT NULL REFERENCES events(uid) ON DELETE CASCADE,
            name TEXT NOT NULL, email TEXT NOT NULL, phone TEXT NOT NULL DEFAULT '',
            organization TEXT NOT NULL DEFAULT '', status TEXT NOT NULL DEFAULT 'CONFIRMADO'
                CHECK(status IN ('CONFIRMADO','ASISTIO','CANCELADO')),
            source TEXT NOT NULL DEFAULT 'WEB', consent_at TEXT,
            created_at TEXT NOT NULL, updated_at TEXT NOT NULL
        )");
        $this->db->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_event_participant_email ON event_participants(event_uid, email)');
        $this->db->exec('CREATE INDEX IF NOT EXISTS idx_event_participants_event ON event_participants(event_uid, status)');
        $this->addColumns('events', ['image' => "TEXT NOT NULL DEFAULT ''"]);
        $this->addColumns('event_pages', [
            'eyebrow' => "TEXT NOT NULL DEFAULT ''", 'hero_image' => "TEXT NOT NULL DEFAULT ''",
            'about_title' => "TEXT NOT NULL DEFAULT ''", 'about_text' => "TEXT NOT NULL DEFAULT ''",
            'highlights' => "TEXT NOT NULL DEFAULT ''", 'cta_label' => "TEXT NOT NULL DEFAULT ''",
        ]);
    }

    private function addColumns(string $table, array $columns): void
    {
        $existing = array_column($this->db->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
        foreach ($columns as $name => $definition) {
            if (!in_array($name, $existing, true)) $this->db->exec("ALTER TABLE $table ADD COLUMN $name $definition");
        }
    }

    private function image(array $input, string $key, string $fallback = ''): string
    {
        $image = trim((string) ($input[$key] ?? $fallback));
        if ($image !== '' && !preg_match('/^fil_[A-Za-z0-9_-]{1,100}$/', $image)) throw new InvalidArgumentException('Imagen no válida. Súbela desde TMPOS.');
        return $image;
    }

    private function localNow(array $project): string
    {
        $timezone = 'America/Santo_Domingo';
        if ($this->schema) {
            try {
                $database = $this->schema->connection($project);
                $statement = $database->query("SELECT valor FROM configuracion WHERE clave='sistema_zona_horaria' LIMIT 1");
                $configured = trim((string) $statement->fetchColumn());
                if ($configured !== '') $timezone = $configured;
            } catch (\Throwable) { /* El proyecto puede no tener esta configuración todavía. */ }
        }
        try { $zone = new DateTimeZone($timezone); }
        catch (\Throwable) { $zone = new DateTimeZone('America/Santo_Domingo'); }
        return (new DateTimeImmutable('now', $zone))->format('Y-m-d H:i');
    }

    private function value(array $input, string $key, int $max, bool $required = false): string
    {
        $value = trim((string) ($input[$key] ?? ''));
        if (($required && $value === '') || mb_strlen($value) > $max) {
            throw new InvalidArgumentException("Revisa el campo $key.");
        }
        return $value;
    }

    private function event(array $project, string $uid): array
    {
        $statement = $this->db->prepare('SELECT * FROM events WHERE uid = ? AND project_uid = ? AND archived = 0');
        $statement->execute([$uid, $project['uid']]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('Evento no encontrado.', 404);
    }

    private function present(array $event): array
    {
        $statement = $this->db->prepare("SELECT COUNT(*) FROM event_participants WHERE event_uid = ? AND status <> 'CANCELADO'");
        $statement->execute([$event['uid']]);
        $event['registered'] = (int) $statement->fetchColumn();
        $event['remaining'] = $event['capacity'] === null ? null : max(0, (int) $event['capacity'] - $event['registered']);
        $event['capacity'] = $event['capacity'] === null ? null : (int) $event['capacity'];
        $event['published'] = (bool) $event['published'];
        $event['registration_open'] = (bool) $event['registration_open'];
        return $event;
    }

    public function list(array $project, bool $public = false): array
    {
        $where = $public ? 'AND published = 1 AND registration_open = 1 AND (event_date > ? OR (event_date = ? AND event_time >= ?))' : '';
        $statement = $this->db->prepare("SELECT * FROM events WHERE project_uid = ? AND archived = 0 $where ORDER BY event_date, event_time, created_at");
        $now = $this->localNow($project);
        $today = substr($now, 0, 10);
        $statement->execute($public ? [$project['uid'], $today, $today, substr($now, 11, 5)] : [$project['uid']]);
        return array_map($this->present(...), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    public function save(array $project, array $input): array
    {
        $uid = trim((string) ($input['uid'] ?? ''));
        $title = $this->value($input, 'title', 180, true);
        $description = $this->value($input, 'description', 4000);
        $speaker = $this->value($input, 'speaker', 160, true);
        $date = $this->value($input, 'event_date', 10, true);
        $time = $this->value($input, 'event_time', 5, true);
        $venue = $this->value($input, 'venue', 240, true);
        $room = $this->value($input, 'room', 120);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || date('Y-m-d', strtotime($date) ?: 0) !== $date) throw new InvalidArgumentException('Fecha del evento no válida.');
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time)) throw new InvalidArgumentException('Hora del evento no válida.');
        $capacity = ($input['unlimited'] ?? false) === true ? null : filter_var($input['capacity'] ?? null, FILTER_VALIDATE_INT);
        if ($capacity !== null && ($capacity === false || $capacity < 1 || $capacity > 1000000)) throw new InvalidArgumentException('Los cupos deben ser mayores que cero o ilimitados.');
        $published = ($input['published'] ?? false) === true ? 1 : 0;
        $open = ($input['registration_open'] ?? true) === true ? 1 : 0;
        $now = Support::now();
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $current = $uid !== '' ? $this->event($project, $uid) : null;
            $image = $this->image($input, 'image', (string) ($current['image'] ?? ''));
            if ($capacity !== null && $current && $capacity < $this->present($current)['registered']) throw new InvalidArgumentException('No puedes reducir los cupos por debajo de los inscritos.');
            if ($current) {
                $this->db->prepare('UPDATE events SET title=?, description=?, image=?, speaker=?, event_date=?, event_time=?, venue=?, room=?, capacity=?, published=?, registration_open=?, updated_at=? WHERE uid=? AND project_uid=?')
                    ->execute([$title, $description, $image, $speaker, $date, $time, $venue, $room, $capacity, $published, $open, $now, $uid, $project['uid']]);
            } else {
                $uid = Support::uid('evt_');
                $this->db->prepare('INSERT INTO events(uid,project_uid,title,description,image,speaker,event_date,event_time,venue,room,capacity,published,registration_open,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$uid, $project['uid'], $title, $description, $image, $speaker, $date, $time, $venue, $room, $capacity, $published, $open, $now, $now]);
            }
            $saved = $this->present($this->event($project, $uid));
            $this->db->commit();
            return $saved;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function archive(array $project, string $uid): array
    {
        $this->event($project, $uid);
        $this->db->prepare('UPDATE events SET archived=1,published=0,registration_open=0,updated_at=? WHERE uid=? AND project_uid=?')
            ->execute([Support::now(), $uid, $project['uid']]);
        return ['archived' => true];
    }

    public function delete(array $project, string $uid): array
    {
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $this->event($project, $uid);
            $participants = $this->db->prepare('DELETE FROM event_participants WHERE event_uid=? AND project_uid=?');
            $participants->execute([$uid, $project['uid']]);
            $deletedParticipants = $participants->rowCount();
            $this->db->prepare('DELETE FROM events WHERE uid=? AND project_uid=?')->execute([$uid, $project['uid']]);
            $this->db->commit();
            return ['deleted' => true, 'participants_deleted' => $deletedParticipants];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function participants(array $project, string $uid): array
    {
        $this->event($project, $uid);
        $statement = $this->db->prepare('SELECT uid,name,email,phone,organization,status,source,created_at FROM event_participants WHERE project_uid=? AND event_uid=? ORDER BY created_at DESC');
        $statement->execute([$project['uid'], $uid]);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function register(array $project, array $input, bool $public): array
    {
        $uid = $this->value($input, 'event_uid', 100, true);
        $name = $this->value($input, 'name', 160, true);
        $email = mb_strtolower($this->value($input, 'email', 180, true));
        $phone = $this->value($input, 'phone', 30);
        $organization = $this->value($input, 'organization', 180);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Introduce un correo válido.');
        if ($public && ($input['consent'] ?? '') !== '1') throw new InvalidArgumentException('Confirma el uso de tus datos para gestionar la inscripción.');
        if ($public && trim((string) ($input['website'] ?? '')) !== '') throw new InvalidArgumentException('No se pudo completar la inscripción.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $event = $this->present($this->event($project, $uid));
            if ($public && (!$event['published'] || !$event['registration_open'] || ($event['event_date'] . ' ' . $event['event_time']) < $this->localNow($project))) throw new RuntimeException('Las inscripciones están cerradas.', 409);
            $statement = $this->db->prepare('SELECT uid,status FROM event_participants WHERE event_uid=? AND email=?');
            $statement->execute([$uid, $email]);
            $existing = $statement->fetch(PDO::FETCH_ASSOC);
            if ($existing && $existing['status'] !== 'CANCELADO') throw new RuntimeException('Este correo ya está inscrito en el evento.', 409);
            if ($event['remaining'] !== null && $event['remaining'] < 1) throw new RuntimeException('Los cupos de este evento están completos.', 409);
            $now = Support::now();
            if ($existing) {
                $this->db->prepare("UPDATE event_participants SET name=?,phone=?,organization=?,status='CONFIRMADO',source=?,consent_at=?,updated_at=? WHERE uid=?")
                    ->execute([$name, $phone, $organization, $public ? 'WEB' : 'MANUAL', $public ? $now : null, $now, $existing['uid']]);
                $participantUid = $existing['uid'];
            } else {
                $participantUid = Support::uid('ept_');
                $this->db->prepare('INSERT INTO event_participants(uid,project_uid,event_uid,name,email,phone,organization,status,source,consent_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)')
                    ->execute([$participantUid, $project['uid'], $uid, $name, $email, $phone, $organization, 'CONFIRMADO', $public ? 'WEB' : 'MANUAL', $public ? $now : null, $now, $now]);
            }
            $this->db->commit();
            return ['uid' => $participantUid, 'event_uid' => $uid, 'status' => 'CONFIRMADO'];
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
    }

    public function attendance(array $project, string $uid, string $status): array
    {
        if (!in_array($status, ['CONFIRMADO', 'ASISTIO', 'CANCELADO'], true)) throw new InvalidArgumentException('Estado no válido.');
        $this->db->exec('BEGIN IMMEDIATE');
        try {
            $statement = $this->db->prepare('SELECT event_uid,status FROM event_participants WHERE uid=? AND project_uid=?');
            $statement->execute([$uid, $project['uid']]);
            $participant = $statement->fetch(PDO::FETCH_ASSOC) ?: throw new RuntimeException('Participante no encontrado.', 404);
            if ($status !== 'CANCELADO' && $participant['status'] === 'CANCELADO') {
                $event = $this->present($this->event($project, $participant['event_uid']));
                if ($event['remaining'] !== null && $event['remaining'] < 1) throw new RuntimeException('No quedan cupos.', 409);
            }
            $this->db->prepare('UPDATE event_participants SET status=?,updated_at=? WHERE uid=? AND project_uid=?')->execute([$status, Support::now(), $uid, $project['uid']]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $error;
        }
        return ['updated' => true];
    }

    public function page(array $project, ?array $input = null): array
    {
        if ($input !== null) {
            $title = $this->value($input, 'title', 160, true);
            $intro = $this->value($input, 'introduction', 1000);
            $color = $this->value($input, 'color', 7, true);
            $current = $this->page($project);
            $eyebrow = $this->value($input + $current, 'eyebrow', 90);
            $heroImage = $this->image($input, 'hero_image', (string) $current['hero_image']);
            $aboutTitle = $this->value($input + $current, 'about_title', 160);
            $aboutText = $this->value($input + $current, 'about_text', 1600);
            $highlights = $this->value($input + $current, 'highlights', 600);
            $ctaLabel = $this->value($input + $current, 'cta_label', 50);
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) throw new InvalidArgumentException('Color no válido.');
            $this->db->prepare('INSERT INTO event_pages(project_uid,enabled,title,introduction,color,eyebrow,hero_image,about_title,about_text,highlights,cta_label,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON CONFLICT(project_uid) DO UPDATE SET enabled=excluded.enabled,title=excluded.title,introduction=excluded.introduction,color=excluded.color,eyebrow=excluded.eyebrow,hero_image=excluded.hero_image,about_title=excluded.about_title,about_text=excluded.about_text,highlights=excluded.highlights,cta_label=excluded.cta_label,updated_at=excluded.updated_at')
                ->execute([$project['uid'], ($input['enabled'] ?? false) === true ? 1 : 0, $title, $intro, $color, $eyebrow, $heroImage, $aboutTitle, $aboutText, $highlights, $ctaLabel, Support::now()]);
        }
        $statement = $this->db->prepare('SELECT enabled,title,introduction,color,eyebrow,hero_image,about_title,about_text,highlights,cta_label FROM event_pages WHERE project_uid=?');
        $statement->execute([$project['uid']]);
        $page = $statement->fetch(PDO::FETCH_ASSOC) ?: ['enabled' => 0, 'title' => 'Próximos eventos', 'introduction' => '', 'color' => '#2563eb', 'eyebrow' => '', 'hero_image' => '', 'about_title' => '', 'about_text' => '', 'highlights' => '', 'cta_label' => 'Explorar eventos'];
        $page['enabled'] = (bool) $page['enabled'];
        $page['url'] = rtrim((string) ($this->config['url'] ?? ''), '/') . '/eventos/' . rawurlencode((string) $project['slug']);
        return $page;
    }

    public function handle(array $project, string $operation, array $input): mixed
    {
        return match ($operation) {
            'list' => $this->list($project),
            'save' => $this->save($project, $input),
            'archive' => ($input['permanent'] ?? false) === true
                ? $this->delete($project, (string) ($input['uid'] ?? ''))
                : $this->archive($project, (string) ($input['uid'] ?? '')),
            'delete' => $this->delete($project, (string) ($input['uid'] ?? '')),
            'participants' => $this->participants($project, (string) ($input['event_uid'] ?? '')),
            'register' => $this->register($project, $input, false),
            'attendance' => $this->attendance($project, (string) ($input['uid'] ?? ''), (string) ($input['status'] ?? '')),
            'page' => $this->page($project),
            'page-save' => $this->page($project, $input),
            default => throw new RuntimeException('Operación de eventos no disponible.', 404),
        };
    }
}
