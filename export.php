<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
require_login();

$format = strtolower((string)($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv','xlsx'], true)) $format = 'csv';

$q = trim((string)($_GET['q'] ?? ''));
$state = (string)($_GET['state'] ?? '');
$year = (string)($_GET['year'] ?? '');

$where = [];
$args = [];
if ($q !== '') {
    $where[] = "(p.project_code LIKE ? OR p.project_name LIKE ? OR i.code LIKE ? OR i.name LIKE ? OR s.country LIKE ? OR su.name LIKE ? OR r.name LIKE ? OR a.name LIKE ?)";
    for ($i=0; $i<8; $i++) $args[] = "%{$q}%";
}
if (in_array($state, ['draft','review','approval','approved'], true)) {
    $where[] = "s.state=?";
    $args[] = $state;
}
if (ctype_digit($year)) {
    $where[] = "s.reporting_year=?";
    $args[] = (int)$year;
}

$sql = "SELECT
    s.id,
    p.project_code,
    p.project_name,
    p.region,
    p.countries AS project_countries,
    i.code AS indicator_code,
    i.name AS indicator_name,
    i.level AS indicator_level,
    s.reporting_year,
    s.country,
    s.reported_value,
    s.unit,
    s.disaggregation,
    s.data_source,
    s.methodology,
    s.notes,
    s.evidence_path,
    s.state,
    su.name AS submitter,
    su.username AS submitter_username,
    su.email AS submitter_email,
    r.name AS reviewer,
    r.username AS reviewer_username,
    r.email AS reviewer_email,
    a.name AS approver,
    a.username AS approver_username,
    a.email AS approver_email,
    s.created_at,
    s.updated_at,
    s.approved_at
FROM submissions s
JOIN projects p ON p.id=s.project_id
JOIN indicators i ON i.id=s.indicator_id
JOIN users su ON su.id=s.submitted_by
LEFT JOIN users r ON r.id=s.reviewer_id
LEFT JOIN users a ON a.id=s.approver_id
" . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
ORDER BY s.updated_at DESC, s.id DESC";

$st = db()->prepare($sql);
$st->execute($args);
$rows = $st->fetchAll();

$headers = [
    'Submission ID','Project Code','Project Name','Region','Project Countries',
    'Indicator Code','Indicator Name','Indicator Level','Reporting Year','Country',
    'Reported Value','Unit','Disaggregation / Details','Data Source','Calculation Methodology',
    'Notes','Evidence File','Workflow Status','Submitter','Submitter Username','Submitter Email',
    'Reviewer','Reviewer Username','Reviewer Email','Approver','Approver Username','Approver Email',
    'Created At','Updated At','Approved At'
];

$data = [$headers];
foreach ($rows as $r) {
    $data[] = [
        (int)$r['id'], $r['project_code'], $r['project_name'], $r['region'], $r['project_countries'],
        $r['indicator_code'], $r['indicator_name'], $r['indicator_level'], (int)$r['reporting_year'], $r['country'],
        (float)$r['reported_value'], $r['unit'], $r['disaggregation'], $r['data_source'], $r['methodology'],
        $r['notes'], $r['evidence_path'], workflow_label((string)$r['state']), $r['submitter'], $r['submitter_username'], $r['submitter_email'],
        $r['reviewer'], $r['reviewer_username'], $r['reviewer_email'], $r['approver'], $r['approver_username'], $r['approver_email'],
        $r['created_at'], $r['updated_at'], $r['approved_at']
    ];
}

$stamp = date('Ymd_His');
$filterLabel = $state ? '_' . preg_replace('/[^A-Za-z0-9_-]+/', '-', workflow_label($state)) : '';

function xml_escape(string $v): string {
    return htmlspecialchars($v, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_col(int $n): string {
    $s='';
    while ($n > 0) { $n--; $s = chr(65 + ($n % 26)) . $s; $n = intdiv($n, 26); }
    return $s;
}

function build_xlsx(array $data): string {
    if (!class_exists('ZipArchive')) {
        throw new RuntimeException('PHP ZipArchive is not enabled. Use CSV export or enable the PHP zip extension in XAMPP.');
    }
    $tmp = tempnam(sys_get_temp_dir(), 'islands_xlsx_');
    $zip = new ZipArchive();
    if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new RuntimeException('Unable to create Excel workbook.');

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
        . '</Types>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>';
    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="GEB Submissions" sheetId="1" r:id="rId1"/></sheets></workbook>';
    $wbRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
        . '</Relationships>';
    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><sz val="11"/><name val="Aptos"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="solid"><fgColor rgb="245A86"/><bgColor indexed="64"/></patternFill></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/><xf numFmtId="0" fontId="1" fillId="1" borderId="0"><alignment horizontal="center"/></xf></cellXfs>'
        . '</styleSheet>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<autoFilter ref="A1:AD' . count($data) . '"/><sheetData>';
    foreach ($data as $ri => $row) {
        $excelRow = $ri + 1;
        $sheet .= '<row r="' . $excelRow . '">';
        foreach ($row as $ci => $value) {
            $col = xlsx_col($ci + 1);
            $ref = $col . $excelRow;
            $isNumeric = is_int($value) || is_float($value);
            $style = $ri === 0 ? ' s="1"' : '';
            if ($isNumeric && $value !== '') {
                $sheet .= '<c r="' . $ref . '"' . $style . ' t="n"><v>' . xml_escape((string)$value) . '</v></c>';
            } else {
                $sheet .= '<c r="' . $ref . '"' . $style . ' t="inlineStr"><is><t xml:space="preserve">' . xml_escape((string)($value ?? '')) . '</t></is></c>';
            }
        }
        $sheet .= '</row>';
    }
    $sheet .= '</sheetData></worksheet>';

    $zip->addFromString('[Content_Types].xml',$contentTypes);
    $zip->addFromString('_rels/.rels',$rels);
    $zip->addFromString('xl/workbook.xml',$workbook);
    $zip->addFromString('xl/_rels/workbook.xml.rels',$wbRels);
    $zip->addFromString('xl/styles.xml',$styles);
    $zip->addFromString('xl/worksheets/sheet1.xml',$sheet);
    $zip->close();
    return $tmp;
}

if ($format === 'xlsx') {
    try {
        $file = build_xlsx($data);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="ISLANDS_GEB_Submissions' . $filterLabel . '_' . $stamp . '.xlsx"');
        header('Content-Length: ' . filesize($file));
        readfile($file); unlink($file); exit;
    } catch (Throwable $e) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Excel export could not be created: ' . $e->getMessage();
        exit;
    }
}

// UTF-8 CSV with BOM for clean Excel opening.
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="ISLANDS_GEB_Submissions' . $filterLabel . '_' . $stamp . '.csv"');
$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF");
foreach ($data as $row) fputcsv($out, $row);
fclose($out);
exit;
