<?php
/**
 * Parser to get list of country divisions and subdivisions with ISO-3166-2 codes in CSV format from https://en.wikipedia.org/wiki/ISO_3166-2
 *
 * To execute run the command in shell `php parse.php`
 * The result will be saved in files `result/divisions.csv` and `result/subdivisions.csv`
 *
 * @link https://github.com/tigrov/whois
 * @author Sergei Tigrov <rrr-r@ya.ru>
 */

require(__DIR__ . '/simple_html_dom.php');

define('RESULT_DIR', __DIR__ . DIRECTORY_SEPARATOR . 'result');
define('CSV_DELIMITER', ';');

// CSV file headers
$divisionsHeader = ['ISO-3166-1', 'ISO-3166-2', 'Name1', 'Name2', 'Wikipedia', 'Type'];
$subdivisionsHeader = ['ISO-3166-1', 'ISO-3166-2', 'ISO Region', 'Name1', 'Name2', 'Wikipedia', 'Type'];

$divisionsCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'divisions.csv', 'w');
$subdivisionsCsv = fopen(RESULT_DIR . DIRECTORY_SEPARATOR . 'subdivisions.csv', 'w');

fputcsv($divisionsCsv, $divisionsHeader, CSV_DELIMITER);
fputcsv($subdivisionsCsv, $subdivisionsHeader, CSV_DELIMITER);

$countryLinks = GetCountryLinks();

foreach ($countryLinks as $countryCode => $isoData) {
    /**
     * @var simple_html_dom_node $html
     * @var simple_html_dom_node $table
     * @var simple_html_dom_node[] $trNodes
     * @var simple_html_dom_node[] $tdNodes
     * @var simple_html_dom_node $codeNode
     * @var simple_html_dom_node $typeNode
     * @var simple_html_dom_node $url
     */

    echo $countryCode . ': ' . $isoData['url'] . PHP_EOL;

    $html = file_get_html('https://en.wikipedia.org' . $isoData['url'], null, null, null);

    // Parse divisions
    // Skip France, see below
    if ($countryCode != 'FR') {
        $table = $html->find('table[class=wikitable sortable]', 0);
        $trNodes = $table->find('tr');
        $typeIndex = GetTypeIndex($trNodes[0]);
        unset($trNodes[0]);

        foreach ($trNodes as $tr) {
            $tdNodes = $tr->childNodes();
            $codeNode = $tdNodes[0]->find('[style*=monospace]') ?: $tdNodes[0];

            list(, $isoCode) = explode('-', $codeNode->text());

            $row = [$countryCode, $isoCode];

            if ($url = $tr->find('a[title]', 0)) {
                $row[] = $url->title;
                $row[] = $url->text();
                $row[] = 'https://en.wikipedia.org' . $url->href;
            } else {
                $name = $tdNodes[1]->text();
                $row[] = $name;
                $row[] = $name;
                $row[] = '';
            }

            $row[] = $typeIndex ? $tdNodes[$typeIndex]->text() : $isoData['type'];

            $row = array_map('trim', $row);

            fputcsv($divisionsCsv, $row, CSV_DELIMITER);
        }
    }

    // Parse subdivisions
    $parentHeader = $html->find('table[class=wikitable sortable] th[plaintext=Parent subdivision]', 0)
        ?: $html->find('table[class=wikitable sortable] th[plaintext^=In ]', 0);

    if ($parentHeader) {
        $table = $parentHeader->parent()->parent();
        $trNodes = $table->find('tr');
        $typeIndex = GetTypeIndex($trNodes[0]);
        $parentIndex = $parentHeader->get_index();
        unset($trNodes[0]);

        foreach ($trNodes as $tr) {
            $tdNodes = $tr->childNodes();
            $codeNode = $tdNodes[$parentIndex]->find('[style*=monospace]') ?: $tdNodes[$parentIndex];
            $parentIso = $codeNode->text();
            if (strpos($parentIso, '-')) {
                list(, $parentIso) = explode('-', $parentIso);
            }

            $codeNode = $tdNodes[0]->find('[style*=monospace]') ?: $tdNodes[0];
            list(, $isoCode) = explode('-', $codeNode->text());

            $row = [$countryCode, $isoCode, $parentIso];

            if ($url = $tr->find('a[title]', 0)) {
                RemoveHidden($url);
                $row[] = $url->title;
                $row[] = $url->text();
                $row[] = 'https://en.wikipedia.org' . $url->href;
            } else {
                RemoveHidden($tdNodes[1]);
                $name = $tdNodes[1]->text();
                $row[] = $name;
                $row[] = $name;
                $row[] = '';
            }

            $row[] = $typeIndex ? $tdNodes[$typeIndex]->text() : $isoData['subtype'];

            $row = array_map('trim', $row);

            fputcsv($subdivisionsCsv, $row, CSV_DELIMITER);
        }
    }
}

// Add France divisions (since January 1 2016)
foreach (GetFranceDivisions() as $isoCode => $isoDate) {
    $row = ['FR', $isoCode, $isoDate['name'], isset($isoDate['name2']) ? $isoDate['name2'] : $isoDate['name'], $isoDate['url'], 'metropolitan region'];

    fputcsv($divisionsCsv, $row, CSV_DELIMITER);
}

fclose($divisionsCsv);
fclose($subdivisionsCsv);

/**
 * @return array
 */
function GetCountryLinks() {
    /**
     * @var simple_html_dom_node $html
     * @var simple_html_dom_node $table
     * @var simple_html_dom_node[] $trNodes
     * @var simple_html_dom_node[] $tdNodes
     * @var simple_html_dom_node $url
     */
    $html = file_get_html('https://en.wikipedia.org/wiki/ISO_3166-2', null, null, null);
    $table = $html->find('table[class=wikitable sortable]', 0);
    $trNodes = $table->childNodes();
    unset($trNodes[0]);

    $list = [];
    foreach ($trNodes as $tr) {
        $tdNodes = $tr->childNodes();
        $types = $tdNodes[2]->text();

        // If has divisions
        if ($types != '—') {
            $typeLines = explode("\n", $types);
            $type = explode(' ', $typeLines[0], 2)[1];
            $subtype = count($typeLines) == 2 ? explode(' ', $typeLines[1], 2)[1] : null;
            $url = $tdNodes[0]->find('a[href^=/wiki/ISO_3166-2]', 0);
            $list[$url->text()] = [
                'url' => $url->href,
                'type' => $type,
                'subtype' => $subtype,
            ];
        }
    }

    return $list;
}

/**
 * @param simple_html_dom_node $node
 * @return null|integer
 */
function GetTypeIndex($node) {
    /**
     * @var simple_html_dom_node $type
     */

    $type = $node->find('th[plaintext=Subdivision category]', 0)
        ?: $node->find('th[plaintext=Subdivision type]', 0);

    if ($type) {
        return $type->get_index();
    }

    return null;
}

/**
 * @param simple_html_dom_node $node
 */
function RemoveHidden($node) {
    /**
     * @var simple_html_dom_node $hidden
     */
    if ($hidden = $node->find('[style=display:none;]')) {
        $hidden->outertext = '';
    }
}

/**
 * France divisions since January 1, 2016
 *
 * @return array
 */
function GetFranceDivisions() {
    return [
        'ARA' => ['url' => 'https://fr.wikipedia.org/wiki/Auvergne-Rh%C3%B4ne-Alpes', 'name' => 'Auvergne-Rhône-Alpes'],
        'BFC' => ['url' => 'https://fr.wikipedia.org/wiki/Bourgogne-Franche-Comt%C3%A9', 'name' => 'Bourgogne-Franche-Comté'],
        'BRE' => ['url' => 'https://fr.wikipedia.org/wiki/Bretagne', 'name' => 'Bretagne'],
        'CVL' => ['url' => 'https://fr.wikipedia.org/wiki/Centre-Val_de_Loire', 'name' => 'Centre-Val de Loire'],
        'COR' => ['url' => 'https://fr.wikipedia.org/wiki/Corse', 'name' => 'Corse'],
        'GES' => ['url' => 'https://fr.wikipedia.org/wiki/Grand_Est', 'name' => 'Grand Est'],
        'HDF' => ['url' => 'https://fr.wikipedia.org/wiki/Hauts-de-France', 'name' => 'Hauts-de-France'],
        'IDF' => ['url' => 'https://fr.wikipedia.org/wiki/%C3%8Ele-de-France', 'name' => 'Île-de-France'],
        'NOR' => ['url' => 'https://fr.wikipedia.org/wiki/Normandie', 'name' => 'Normandie'],
        'NAQ' => ['url' => 'https://fr.wikipedia.org/wiki/Nouvelle-Aquitaine', 'name' => 'Nouvelle-Aquitaine'],
        'OCC' => ['url' => 'https://fr.wikipedia.org/wiki/Occitanie_(r%C3%A9gion_administrative)', 'name' => 'Occitanie (région administrative)', 'name2' => 'Occitanie'],
        'PDL' => ['url' => 'https://fr.wikipedia.org/wiki/Pays_de_la_Loire', 'name' => 'Pays de la Loire'],
        'PAC' => ['url' => 'https://fr.wikipedia.org/wiki/Provence-Alpes-C%C3%B4te_d%27Azur', 'name' => "Provence-Alpes-Côte d'Azur"],
    ];
}