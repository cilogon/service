<?php

/***
 * This script scans all PHP files for gettext() / _() function calls and
 * generates .po files for multiple languages. You can specify the target
 * languages for translation by changing the TARGET_LANGS define below.
 * 
 * This script uses the AWS Translate API for translation. This API requires
 * AWS authentication. See https://github.com/cilogon/aws-cli-setup
 * for how to authenticate with your UIUC account, then log in to AWS Ohio
 * (us-east-2). You must have an active AWS authn session.
 */

require_once __DIR__ . '/vendor/autoload.php';

use Gettext\Scanner\PhpScanner;
use Gettext\Translations;
use Gettext\Loader\PoLoader;
use Gettext\Generator\PoGenerator;
use Gettext\Generator\MoGenerator;
use Aws\Exception\AwsException;

// A list of target languages for tranlation.
// NOTE: 'en' (English) must ALWAYS be a target.
// 've' is the upside-down English version (optional).
define('TARGET_LANGS', array('en', 've', 'fr', 'de'));

/***
 * This function takes a string and rotates it 180 degrees so it looks like
 * it has been flipped upside-down.
 */
function flipString($strtoflip)
{
    $retstr = '';
    for ($i = strlen($strtoflip)-1; $i >= 0; $i--) {
        $retstr .= flipChar(substr($strtoflip, $i, 1));
    }
    return $retstr;
}

/***
 * This function takes a single character and rotates it 180 degrees to it
 * looks like it has been flipped upside-down. The following characters are
 * used for the flip:
 *
 * Ɐ ꓭ Ɔ ꓷ Ǝ Ⅎ ꓨ H I ſ ꓘ ꓶ Ā N O Ԁ Ꝺ ꓤ S ꓕ ꓵ ꓥ M X ⅄ Z ɐ ā ɔ Ă ǝ ɟ ƃ ɥ ı̣ ɾ̣ ʞ ן ɯ ă o d b ɹ s ʇ n ʌ ʍ x ʎ z W q p u ' ¡ ⅋ ‾
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

// Ensure that this script runs only from the command line
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

// Run 'composer update' to pull in any library dependencies
$output = null;
if (
    (exec('composer -V', $output, $result_code) === false) ||
    ($result_code != 0)
) {
    echo "Unable to find 'composer' command.\n";
    echo "Please see https://getcomposer.org/doc/00-intro.md for installation.\n";
    exit;
}
exec('composer update');

// Create a new AWS TranslateClient - requires AWS authentication
$awsclient = new Aws\Translate\TranslateClient([
    'profile' => 'us-east-2',
    'region' => 'us-east-2',
    'version' => '2017-07-01'
]);

// Scan all php files for gettext() / _() function calls
$phpScanner = new PhpScanner(
    Translations::create('cilogon')
);
$phpScanner->setDefaultDomain('cilogon');
foreach (glob('./{*,*/*,*/*/*,*/*/*/*,*/*/*/*/*,*/*/*/*/*/*}.php', GLOB_BRACE) as $file) {
    // Skip non-cilogon vendor libraries
    if (
        (preg_match('%\./vendor/%', $file)) &&
        (!preg_match('%\./vendor/cilogon/%', $file))
    ) {
        continue;
    }
    $phpScanner->scanFile($file);
}
list('cilogon' => $translations) = $phpScanner->getTranslations();

// Loop through the TARGET_LANGS array, creating .po files for each
foreach (TARGET_LANGS as $lang) {
    echo "Translating to '$lang'\n";
    $translatedString = '';

    foreach ($translations as $translation) {
        if ($lang == 'en') { // For English, just copy the source string
            $translatedString = $translation->getOriginal();
        } elseif ($lang == 've') { // For upside-down, flip the source string
            $translatedString = flipString($translation->getOriginal());
        } else { // Use AWS Translate API for all other languages
            try {
                $result = $awsclient->translateText([
                    'SourceLanguageCode' => 'en',
                    'TargetLanguageCode' => $lang,
                    'Text' => $translation->getOriginal(),
                ]);
                $translatedString = $result->get('TranslatedText');
            } catch (AwsException $e) {
                echo $e->getMessage() . "\n";
                exit;
            }
        }

        $translation->translate($translatedString);
    }

    $poGenerator = new PoGenerator();
    if (!is_dir('locale/' . $lang . '/LC_MESSAGES')) {
        mkdir('locale/' . $lang . '/LC_MESSAGES', 0755, true);
    }
    $poGenerator->generateFile($translations, 'locale/' . $lang . '/LC_MESSAGES/cilogon.po');
}

echo "Done!\n";
