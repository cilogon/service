<?php

// Before you run this script you must run:
//
//     composer update

require_once __DIR__ . '/vendor/autoload.php';

use Gettext\Scanner\PhpScanner;
use Gettext\Translations;
use Gettext\Loader\PoLoader;
use Gettext\Generator\PoGenerator;

function flipString($strtoflip)
{
    $retstr = '';
    for ($i = strlen($strtoflip)-1; $i >= 0; $i--) {
        $retstr .= flipChar(substr($strtoflip, $i, 1));
    }
    return $retstr;
}

/*
"Ɐ ꓭ Ɔ ꓷ Ǝ Ⅎ ꓨ H I ſ ꓘ ꓶ Ā N O Ԁ Ꝺ ꓤ S ꓕ ꓵ ꓥ M X ⅄ Z ɐ ā ɔ Ă ǝ ɟ ƃ ɥ ı̣ ɾ̣ ʞ ן ɯ ă o d b ɹ s ʇ n ʌ ʍ x ʎ z W q p u ' ¡ ⅋ ‾"
 */

function flipChar($chartoflip)
{
    $flipTable = array(
        'a' => 'ɐ',
        'b' => 'q',
        'c' => 'ɔ',
        'd' => 'p',
        'e' => 'ǝ',
        'f' => 'ɟ',
        'g' => 'ƃ',
        'h' => 'ɥ',
        'i' => 'ᴉ',
        'j' => 'ɾ',
        'k' => 'ʞ',
        'l' => 'ן',
        'm' => 'ɯ',
        'n' => 'u',
        'p' => 'd',
        'q' => 'b',
        'r' => 'ɹ',
        't' => 'ʇ',
        'u' => 'n',
        'v' => 'ʌ',
        'w' => 'ʍ',
        'y' => 'ʎ',
        'A' => '∀',
        'B' => 'ꓭ',
        'C' => 'Ɔ',
        'D' => 'ꓷ',
        'E' => 'Ǝ',
        'F' => 'Ⅎ',
        'G' => 'ꓨ',
        'H' => 'H',
        'I' => 'I',
        'J' => 'ſ',
        'K' => 'ꓘ',
        'L' => 'ꓶ',
        'M' => 'W',
        'N' => 'N',
        'P' => 'Ԁ',
        'Q' => 'Ꝺ',
        'R' => 'ꓤ',
        'T' => 'ꓕ',
        'U' => 'ꓵ',
        'V' => 'Λ',
        'W' => 'M',
        'Y' => '⅄',
        '1' => 'Ɩ',
        '2' => 'ᄅ',
        '3' => 'Ɛ',
        '4' => 'ㄣ',
        '5' => 'ϛ',
        '6' => '9',
        '7' => 'ㄥ',
        '8' => '8',
        '9' => '6',
        '0' => '0',
        '.' => '˙',
        ',' => '\'',
        '\'' => ',',
        '"' => '˶',
        '`' => ',',
        '?' => '¿',
        '!' => '¡',
        '[' => ']',
        ']' => '[',
        '(' => ')',
        ')' => '(',
        '{' => '}',
        '}' => '{',
        '<' => '>',
        '>' => '<',
        '&' => '⅋',
        '_' => '‾',
        '∴' => '∵',
        '⁅' => '⁆'
    );

    $flipTableFlipped = array(
        'ɐ' => 'a',
        'q' => 'b',
        'ɔ' => 'c',
        'p' => 'd',
        'ǝ' => 'e',
        'ɟ' => 'f',
        'ƃ' => 'g',
        'ɥ' => 'h',
        'ᴉ' => 'i',
        'ɾ' => 'j',
        'ʞ' => 'k',
        'ן' => 'l',
        'ɯ' => 'm',
        'u' => 'n',
        'd' => 'p',
        'b' => 'q',
        'ɹ' => 'r',
        'ʇ' => 't',
        'n' => 'u',
        'ʌ' => 'v',
        'ʍ' => 'w',
        'ʎ' => 'y',
        '∀' => 'A',
        'B' => 'ꓭ',
        'Ɔ' => 'C',
        'ꓷ' => 'D',
        'Ǝ' => 'E',
        'Ⅎ' => 'F',
        'ꓨ' => 'G',
        'H' => 'H',
        'I' => 'I',
        'ſ' => 'J',
        'ꓘ' => 'K',
        'ꓶ' => 'L',
        'W' => 'M',
        'N' => 'N',
        'Ԁ' => 'P',
        'Ꝺ' => 'Q',
        'ꓤ' => 'R',
        'ꓕ' => 'T',
        'ꓵ' => 'U',
        'Λ' => 'V',
        'M' => 'W',
        '⅄' => 'Y',
        'Ɩ' => '1',
        'ᄅ' => '2',
        'Ɛ' => '3',
        'ㄣ' => '4',
        'ϛ' => '5',
        '9' => '6',
        'ㄥ' => '7',
        '8' => '8',
        '6' => '9',
        '0' => '0',
        '˙' => '.',
        '\'' => ',',
        ',' => '\'',
        '˶' => '"',
        ',' => '`',
        '¿' => '?',
        '¡' => '!',
        ']' => '[',
        '[' => ']',
        ')' => '(',
        '(' => ')',
        '}' => '{',
        '{' => '}',
        '>' => '<',
        '<' => '>',
        '⅋' => '&',
        '‾' => '_',
        '∵' => '∴',
        '⁆' => '⁅'
    );

    if (array_key_exists($chartoflip, $flipTable)) {
        return $flipTable[$chartoflip];
    } elseif (array_key_exists($chartoflip, $flipTableFlipped)) {
        return $flipTableFlipped[$chartoflip];
    } elseif (array_key_exists(strtolower($chartoflip), $flipTable)) {
        return $flipTable[strtolower($chartoflip)];
    } else {
        return $chartoflip;
    }
}

// Run this script only from the command line
if (strlen($_SERVER['DOCUMENT_ROOT']) > 0) {
    exit;
}

// Run this script only from the script directory
if (__DIR__ != getcwd()) {
    echo 'Please run this script from the ' . __DIR__ . ' directory.' . "\n";
    exit;
}

// Check if the service-lib repo exists at the same dir level
if (!file_exists('../service-lib/composer.json')) {
    echo "Please run the following commands to check out the service-lib";
    echo "repository at the same directory level as this repository.";
    echo "    pushd ..";
    echo "    git clone git@github.com:cilogon/service-lib.git";
    echo "    popd";
    echo "Then run this script again.";
    exit;
}

// Create symlink to service-lib if it doesn't exist
if (!is_dir('vendor/cilogon')) {
    mkdir('vendor/cilogon', 0755, true);
}
chdir('vendor/cilogon');
if (!is_link('service-lib')) {
    system('rm -rf service-lib');
    symlink('../../../service-lib', 'service-lib');
}
chdir('../..');


// Scan all php files for gettext() / _() function calls
$phpScanner = new PhpScanner(
    Translations::create('cilogon')
);
$phpScanner->setDefaultDomain('cilogon');
foreach (glob('./{*,*/*,*/*/*,*/*/*/*,*/*/*/*/*}.php', GLOB_BRACE) as $file) {
    $phpScanner->scanFile($file);
}
list('cilogon' => $translations) = $phpScanner->getTranslations();

// First, just copy original to translation for en_US
foreach ($translations as $translation) {
    $translation->translate($translation->getOriginal());
}
$poGenerator_en_US = new PoGenerator();
if (!is_dir('locale/en_US')) {
    mkdir('locale/en_US', 0755, true);
}
$poGenerator_en_US->generateFile($translations, 'locale/en_US/cilogon.po');

// Next, flip the text upside down
foreach ($translations as $translation) {
    $translation->translate(flipString($translation->getOriginal()));
}
$poGenerator_en_UM = new PoGenerator();
if (!is_dir('locale/en_UM')) {
    mkdir('locale/en_UM', 0755, true);
}
$poGenerator_en_UM->generateFile($translations, 'locale/en_UM/cilogon.po');



