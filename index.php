<?php
/**
 * mysql2html
 *
 * database specification documenter
 *
 */

$document['name']       = '';
$document['systemname'] = '';
$document['author']     = '';

$database['host']       = '';
$database['dbname']     = '';
$database['user']       = '';
$database['password']   = '';

while (@ob_end_clean());

try{
    $pdo = new PDO("mysql:host={$database['host']};dbname={$database['dbname']}", $database['user'], $database['password']);
} catch (PDOException $e) {
    die('cannot connect database');
}
$pdo->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
$pdo->query('SET NAMES utf8');
if (!$pdo->query('show tables')) die('no tables');

$tables = array();
$columns = array();
$fkeys = array();
$unique_keys = array();
$keys = array();

$i = 1;
foreach ($pdo->query('show tables')->fetchAll() as $t) {
    $table = $t[0];
    $status = $pdo->query(
        sprintf("show table status from `%s` like '%s'", $database['dbname'], $table)
    )->fetchAll();
    $tables[$table] = $status[0];
    $columns[$table] = $pdo->query(
        sprintf("show full columns from `%s`", $table)
    )->fetchAll();
    $ddl = $pdo->query(sprintf('show create table %s', $table))->fetch();
    $fkeys[$table] = fk_parse($ddl['create table']);
    $unique_keys[$table] = unique_parse($ddl['create table']);
    $keys[$table] = index_parse($ddl['create table']);
}

function fk_parse($createddl)
{
    $fkeys = array();
    foreach (preg_split('/(\r\n|\n|\r)/', $createddl) as $line ) {
        $matches = array();
        $pattern = '/CONSTRAINT `(?P<fkname>[^`]+)` FOREIGN KEY \(`(?P<fkcolumn>[^`]+)`\) REFERENCES `(?P<reftable>[^`]+)` \(`(?P<refcolumn>[^`]+)`\)/';
        if (preg_match($pattern, $line, $matches)) {
            $fkeys[] = $matches;
        }
    }
    return $fkeys;
}

function unique_parse($createddl)
{
    $unique_keys = array('__reverse__'=>array());
    foreach (preg_split('/(\r\n|\n|\r)/', $createddl) as $line ) {
        $matches = array();
        if (preg_match('/UNIQUE KEY `(?P<keyname>[^`]+)` \((?P<keycolumns>[^\)]+)\)/', trim($line), $matches)) {
            $columns = str_replace('`', '', explode(',', $matches['keycolumns']));
            $unique_keys[$matches['keyname']] = str_replace('`', '', explode(',', $matches['keycolumns']));
            foreach ($columns as $column) {
                if (isset($unique_keys['__reverse__'][$column]) == false) {
                    $unique_keys['__reverse__'][$column] = array();
                }
                $unique_keys['__reverse__'][$column][] = $matches['keyname'];
            }
        }
    }
    return $unique_keys;
}

function index_parse($createddl)
{
    $keys = array('__reverse__'=>array());
    foreach (preg_split('/(\r\n|\n|\r)/', $createddl) as $line ) {
        $matches = array();
        if (preg_match('/^KEY `(?P<keyname>[^`]+)` \((?P<keycolumns>[^\)]+)\)/', trim($line), $matches)) {
            $columns = str_replace('`', '', explode(',', $matches['keycolumns']));
            $keys[$matches['keyname']] = str_replace('`', '', explode(',', $matches['keycolumns']));
            foreach ($columns as $column) {
                if (isset($keys['__reverse__'][$column]) == false) {
                    $keys['__reverse__'][$column] = array();
                }
                $keys['__reverse__'][$column][] = $matches['keyname'];
            }
        }
    }
    return $keys;
}

function h($str)
{
    echo nl2br(htmlspecialchars($str."", ENT_QUOTES, 'UTF-8'));
}

function nameja($comment)
{
    $list = preg_split("/(\r\n|\n|\r)/", $comment, 2);
    h($list[0]);
}

function comment($comment)
{
    $list = preg_split("/(\r\n|\n|\r)/", $comment, 2);
    if (count($list) ==2) {
        h($list[1]);
    }
}

function indexies($column, $pkey, $unique_keys, $keys)
{
    $output = array();
    if ($pkey == 'PRI') {
        $output[] = 'PRI';
    }
    if (isset($unique_keys['__reverse__'][$column])) {
        foreach ($unique_keys['__reverse__'][$column] as $keyname) {
            $output[] = $keyname.' *';
        }
    }
    if (isset($keys['__reverse__'][$column])) {
        foreach ($keys['__reverse__'][$column] as $keyname) {
            $output[] = $keyname;
        }
    }
    echo nl2br(join("\n", $output));
}

header('content-type: text/html; charset=UTF-8');
?><!DOCTYPE html>
<html>
<head>
<meta http-equiv="content-type" content="UTF-8">
<title><?php h($document['name']);?>(systemname=<?php h($document['systemname']);?>, dbname=<?php h($database['dbname']);?>)</title>
<link rel="stylesheet" href="style.css" type="text/css" media="all">
<link rel="stylesheet" href="style.print.css" type="text/css" media="print">
</head>

<body>
<div class="outer">
    <div class="documenttitle">
        <h1><?php h($document['name']);?></h1>
        <h2><?php h($document['systemname']);?></h2>
        <h3><?php h(date('Y/m/d'));?><br/ ><?php h($document['author']);?></h3>
    </div>
</div>
<div class="outer pagebreak">
    <div class="informations">
        <h2 class="pagetitle">テーブル一覧</h2>
        <table class="headers">
            <tr>
                <th class="text-column">システム</th>
                <td><?php h($document['systemname']);?></td>
                <th rowspan="2" class="text-column">作成日</th>
                <td rowspan="2" class="date-column"><?php h(date('Y/m/d'));?></td>
            </tr>
            <tr>
                <th class="text-column">スキーマ</th>
                <td><?php h($database['dbname']);?></td>
            </tr>
        </table>
    </div>
    <table id="tables" class="tables">
        <tr>
            <th class="number-column">No.</th>
            <th>論理名</th>
            <th>物理名</th>
            <th>備考</th>
        </tr>
        <?php $i=1;?>
        <?php foreach($tables as $table=>$status): ?>
        <tr data-ref="table-<?php h($table);?>" id="tables-<?php h($table);?>">
            <td class="shortchar"><?php h($i++);?></td>
            <td><?php nameja($status['comment']);?></td>
            <td><?php h($status['name']);?></td>
            <td><?php comment($status['comment']);?></td>
        </tr>
        <?php endforeach; ?>
        <?php while($i<=30): ?>
        <tr>
            <td class="shortchar"><?php h($i++);?></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <?php endwhile; ?>
    </table>
</div>
<?php foreach($tables as $table=>$status): ?>
<div class="outer pagebreak" id="table-<?php h($table);?>">
    <div class="informations">
        <h2 class="pagetitle">テーブル定義書</h2>
        <table class="headers">
            <tr>
                <th class="text-column">論理名</th>
                <td><?php nameja($status['comment']);?></td>
                <th rowspan="2" class="text-column">作成日</th>
                <td rowspan="2" class="date-column"><?php h(date('Y/m/d'));?></td>
            </tr>
            <tr>
                <th class="text-column">物理名</th>
                <td><?php h($status['name']);?></td>
            </tr>
        </table>
    </div>
    <table class="columns">
        <tr>
            <th class="number-column">No.</th>
            <th class="text-column">論理名</th>
            <th class="text-column">物理名</th>
            <th class="text-column">型</th>
            <th class="yn-column">必須</th>
            <th>インデックス</th>
            <th>備考</th>
        </tr>
        <?php $i=1;?>
        <?php foreach($columns[$table] as $column): ?>
        <tr class="column" name="<?php h($column['field']);?>" id="<?php h($table); ?>-<?php h($column['field']);?>">
            <td class="shortchar"><?php h($i++);?></td>
            <td><?php nameja($column['comment']);?></td>
            <td><?php h($column['field']);?></td>
            <td><?php h(str_replace(',', ', ', $column['type']));?></td>
            <td class="shortchar"><?php h($column['null']=='YES'? 'N' : 'Y');?></td>
            <td>
                <?php indexies($column['field'], $column['key'], $unique_keys[$table], $keys[$table]);?>
            </td>
            <td><?php comment($column['comment']);?></td>
        </tr>
        <?php endforeach; ?>
        <?php while($i<=20): ?>
        <tr>
            <td class="shortchar"><?php h($i++);?></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
        </tr>
        <?php endwhile; ?>
    </table>
    <div class="optionals">
        <div class="optionals-fkeys">
            <h3>外部キー</h3>
            <table>
                <tr>
                    <th class="number-column">No.</th>
                    <th class="text-column">カラム</th>
                    <th class="text-column">参照テーブル</th>
                    <th class="text-column">参照カラム</th>
                </tr>
                <?php $j=1;?>
                <?php foreach ( $fkeys[$table] as $fkey): ?>
                <tr class="fkey-column"
                    data-column="<?php h($table);?>-<?php h($fkey['fkcolumn']);?>"
                    data-this="table-<?php h($table);?>"
                    data-refid="<?php h($fkey['reftable']);?>-<?php h($fkey['refcolumn']);?>"
                >
                    <td class="shortchar"><?php h($j++);?></td>
                    <td><?php h($fkey['fkcolumn']);?></td>
                    <td><?php h($fkey['reftable']);?></td>
                    <td><?php h($fkey['refcolumn']);?></td>
                </tr>
                <?php endforeach?>
                <?php while($j<=3): ?>
                <tr>
                    <td class="shortchar"><?php h($j++);?></td>
                    <td></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
        <div class="optionals-indexes">
            <h3>インデックス</h3>
            <table>
                <tr>
                    <th class="number-column">No.</th>
                    <th class="text-column">カラム</th>
                    <th>ユニーク</th>
                </tr>
                <?php $k=1;?>
                <?php foreach($unique_keys[$table] as $keyname=>$uniquekey): ?>
                <?php if ($keyname=='__reverse__') continue; ?>
                <tr class="index-column" data-table="<?php h($table);?>" data-column="<?php h(join(",", $uniquekey));?>">
                    <td class="shortchar"><?php h($k++);?></td>
                    <td><?php nl2br(h(join("\n", $uniquekey)));?></td>
                    <td class="shortchar">Y</td>
                </tr>
                <?php endforeach?>
                <?php foreach($keys[$table] as $keyname=>$key): ?>
                <?php if ($keyname=='__reverse__') continue; ?>
                <tr class="index-column" data-table="<?php h($table);?>" data-column="<?php h(join(",", $key));?>">
                    <td class="shortchar"><?php h($k++);?></td>
                    <td><?php nl2br(h(join("\n", $key)));?></td>
                    <td class="shortchar">N</td>
                </tr>
                <?php endforeach?>
                <?php while($k<=3): ?>
                <tr>
                    <td class="shortchar"><?php h($k++);?></td>
                    <td></td>
                    <td></td>
                </tr>
                <?php endwhile; ?>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>
</body>
</html>
