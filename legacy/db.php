<?php
// Provides only the named public data reads used by these templates.
class PublicRows {
    public $num_rows;
    private $rows;
    private $index = 0;

    function __construct(array $rows) {
        $this->rows = array_values($rows);
        $this->num_rows = count($this->rows);
    }
    function fetch_assoc() { return $this->rows[$this->index++] ?? null; }
    function free() {}
}

class PublicStatement {
    private $conn;
    private $sql;
    private $params = [];
    private $outputs = [];
    private $result;

    function __construct($conn, $sql) { $this->conn = $conn; $this->sql = $sql; }
    function bind_param($types, ...$params) { $this->params = $params; }
    function execute() {
        $this->result = new PublicRows($this->conn->statement($this->sql, $this->params));
        return true;
    }
    function bind_result(&...$outputs) { $this->outputs = $outputs; }
    function fetch() {
        $row = $this->result->fetch_assoc();
        if ($row === null) return false;
        $values = array_values($row);
        foreach ($this->outputs as $index => &$output) $output = $values[$index];
        return true;
    }
    function close() {}
}

function public_text($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function public_image($value) {
    $value = trim((string) $value);
    if (preg_match('~^https://[^\s<>"\x27()\\\\]+$~iD', $value)
        || preg_match('~^/?[a-z0-9_/-]+\.(png|jpe?g|webp|gif)$~iD', $value)) {
        return public_text($value);
    }
    return '';
}

class PublicSiteData {
    private $data;
    function __construct($data) { $this->data = $data; }
    function prepare($sql) { return new PublicStatement($this, $sql); }

    function query($sql) {
        $rows = match (true) {
            str_contains($sql, 'FROM system_settings') => [['setting_value' => $this->data['outreach_status'] ?? 'open']],
            str_contains($sql, 'FROM bookings') => array_map(
                fn($booking) => array_map('public_text', $booking + ['session_time' => '', 'stage_type' => '']),
                $this->data['bookings'] ?? []
            ),
            str_contains($sql, 'FROM highlights') => $this->cards('highlights', 2),
            str_contains($sql, 'FROM events') => $this->cards('events'),
            str_contains($sql, 'FROM updates') => array_map(
                fn($row) => array_map('public_text', array_intersect_key($row, ['tag' => '', 'time_label' => '', 'content' => '']) + ['tag' => '', 'time_label' => '', 'content' => '']),
                array_slice($this->data['content']['updates'] ?? [], 0, 3)
            ),
            str_contains($sql, 'SELECT DISTINCT committee_year') => array_map(
                fn($year) => ['committee_year' => $year],
                array_unique(array_column($this->data['content']['leadership'] ?? [], 'committee_year'), SORT_REGULAR)
            ),
            str_contains($sql, 'FROM outreach_posts') => array_map(function ($post) {
                $post['thumbnail'] = public_image($post['thumbnail'] ?? '');
                $post['author_photo'] = public_image($post['author_photo'] ?? '');
                return $post;
            }, $this->data['outreach'] ?? []),
            str_starts_with($sql, 'SHOW COLUMNS') => [['Field' => 'created_at', 'Type' => 'timestamp']],
            str_contains($sql, 'AS score_month') => $this->months(),
            str_contains($sql, 'AS missing_dates') => [['missing_dates' => 0]],
            default => [],
        };
        if (str_contains($sql, 'SELECT DISTINCT committee_year')) {
            usort($rows, fn($a, $b) => $b['committee_year'] <=> $a['committee_year']);
        }
        return new PublicRows($rows);
    }

    private function cards($section, $limit = null) {
        return array_map(fn($row) => [
            'title' => public_text($row['title'] ?? ''),
            'description' => public_text($row['description'] ?? ''),
            'event_date' => public_text($row['event_date'] ?? ''),
            'image' => public_image($row['image'] ?? ''),
        ], array_slice($this->data['content'][$section] ?? [], 0, $limit));
    }

    private function months() {
        $months = array_values(array_unique(array_filter(array_column($this->data['ranked'] ?? [], 'month'))));
        rsort($months);
        return array_map(fn($month) => ['score_month' => $month], $months);
    }

    function statement($sql, $params) {
        if (str_contains($sql, 'FROM leadership')) {
            $year = $params[0] ?? null;
            $members = array_filter($this->data['content']['leadership'] ?? [], fn($row) =>
                $year === null ? ($row['committee_year'] ?? null) === null : (int) ($row['committee_year'] ?? 0) === (int) $year);
            $order = ['president' => 1, 'general secretary' => 2, 'treasurer' => 3];
            usort($members, fn($a, $b) =>
                (($order[strtolower(trim($a['position']))] ?? 4) <=> ($order[strtolower(trim($b['position']))] ?? 4))
                ?: strcasecmp($a['name'], $b['name']));
            return array_map(fn($row) => [$row['name'], $row['position'], $row['photo'] ?? ''], $members);
        }
        if (!str_contains($sql, 'LEFT JOIN submissions')) return [];
        $groups = [];
        foreach ($this->data['ranked'] ?? [] as $row) {
            $key = $row['group_id'] ?? ($row['type'] . '/' . $row['name']);
            if (!isset($groups[$key])) $groups[$key] = [count($groups) + 1, $row['name'], $row['type'], 0, 0];
            if (!$params || $row['month'] === substr($params[0], 0, 7)) {
                $groups[$key][3] += (int) $row['points'];
                $groups[$key][4] += (int) $row['entries'];
            }
        }
        usort($groups, fn($a, $b) => ($b[3] <=> $a[3]) ?: strcasecmp($a[1], $b[1]));
        return $groups;
    }
}

$conn = new PublicSiteData(json_decode(getenv('NUSHA_PUBLIC_DATA') ?: '{}', true) ?: []);
