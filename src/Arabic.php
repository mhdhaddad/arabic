<?php

namespace Mhdhaddad\Arabic;

class Arabic
{
    const ARABIC_RANGE_START_IN_DECIMAL = 55424;
    const ARABIC_RANGE_END_IN_DECIMAL = 55743;
    private string|null $arGlyphsVowel = null;
    private array|null $arGlyphs = null;

    private function arGlyphsInit(): void
    {
        $this->arGlyphsVowel = 'ًٌٍَُِّْ';

        // Arabic Presentation Forms-B (https://en.wikipedia.org/wiki/Arabic_Presentation_Forms-B)
        // Contextual forms (https://en.wikipedia.org/wiki/Arabic_script_in_Unicode#Contextual_forms)
        // 0- ISOLATED FORM, 1- FINAL FORM, 2- INITIAL FORM, 3- MEDIAL FORM
        $this->arGlyphs = json_decode((string)file_get_contents(__DIR__ . '/ar_glyphs.json'), true);
    }

    /**
     * Find all offsets for arabic texts and return an array of their
     * positions, even indices for start, odds are for last
     * https://utf8-chartable.de/unicode-utf8-table.pl?start=1536&number=128&utf8=dec
     * @param string $inputString
     * @return array
     */
    public function findOffsets(string $inputString): array
    {
        $probArFlag = false;
        $arFlag = false;
        $htmlFlag = false;
        $positions = [];
        $inputStringInDecimal = unpack('C*', $inputString); // an unpacked array from our string, it starts as an ascii, then it

        $i = -1;
        while (++$i < strlen($inputString)) {
            $nextLetterIndex = $i + 1;
            $currentLetterInDecimal = $inputStringInDecimal[$nextLetterIndex];

            // bypass html tags
            if ($currentLetterInDecimal == 60 && $inputStringInDecimal[$i + 2] != 32) {
                $htmlFlag = true;
            } elseif ($htmlFlag && $currentLetterInDecimal == 62) {
                $htmlFlag = false;
            } elseif ($htmlFlag) {
                continue;
            }

            // bypass ! " # $ % & ' ( ) * + , - . / 0 1 2 3 4 5 6 7 8 9 :
            if ($currentLetterInDecimal >= 33 && $currentLetterInDecimal <= 58) {
                continue;
            }

            // bypass Arabic first byte, only if $probArFlag = false, I don't know why
            if (!$probArFlag && ($currentLetterInDecimal == 216 || $currentLetterInDecimal == 217)) {
                $probArFlag = true;
                continue;
            }

            // if it is not the first letter in the loop, set the previous letter in decimal
            $previousLetterInDecimal = $i > 0 ? $inputStringInDecimal[$i] : null;

            if ($probArFlag) {
                $utfDecCode = ($previousLetterInDecimal << 8) + $currentLetterInDecimal;
                if ($utfDecCode >= self::ARABIC_RANGE_START_IN_DECIMAL && $utfDecCode <= self::ARABIC_RANGE_END_IN_DECIMAL) {
                    if (!$arFlag) {
                        $arFlag = true;
                        // include the previous open bracket ( if it is exists
                        $sp = strlen(rtrim(substr($inputString, 0, $i - 1))) - 1;
                        if ($inputString[$sp] == '(') {
                            $positions[] = $sp;
                        } else {
                            $positions[] = $i - 1;
                        }
                    }
                } else {
                    if ($arFlag) {
                        $arFlag = false;
                        $positions[] = $i - 1;
                    }
                }

                $probArFlag = false;
                continue;
            }

            if ($arFlag && !preg_match("/^\s$/", $inputString[$i])) {
                $arFlag = false;
                // tag out the trailer spaces
                $sp = $i - strlen(rtrim(substr($inputString, 0, $i)));
                $positions[] = $i - $sp;
            }
        }

        if ($arFlag) {
            $positions[] = $i;
        }

        return $positions;
    }

    /**
     * Convert Arabic string into glyph joining in UTF-8 hexadecimals stream
     *
     * @param string $str Arabic string in UTF-8 charset
     *
     * @return string Arabic glyph joining in UTF-8 hexadecimals stream
     * @author Khaled Al-Sham'aa <khaled@ar-php.org>
     */
    private function arGlyphsPreConvert($str)
    {
        $this->arGlyphsInit();
        $crntChar = null;
        $prevChar = null;
        $nextChar = null;
        $output = '';
        $number = '';
        $chars = array();

        $open_range = ')]>}';
        $close_range = '([<{';

        $_temp = mb_strlen($str);

        // split the given string to an array of chars
        for ($i = 0; $i < $_temp; $i++) {
            $chars[] = mb_substr($str, $i, 1);
        }

        $max = count($chars);

        // scan the array of chars backward to flip the sequence of Arabic chars in the string
        for ($i = $max - 1; $i >= 0; $i--) {
            $crntChar = $chars[$i];

            // by default assume the letter form is isolated
            $form = 0;

            // set the prevChar by ignore tashkeel (max of two harakat), let it be space if we process the last char
            if ($i > 0) {
                $prevChar = $chars[$i - 1];
                if (mb_strpos($this->arGlyphsVowel, $prevChar) !== false && $i > 1) {
                    $prevChar = $chars[$i - 2];

                    if (mb_strpos($this->arGlyphsVowel, $prevChar) !== false && $i > 2) {
                        $prevChar = $chars[$i - 3];
                    }
                }
            } else {
                $prevChar = ' ';
            }

            // if it is a digit, then keep adding it to the number in the correct order from left to right
            // once finish, push it to the output array as a whole number then reset the number value to empty
            if (is_numeric($crntChar)) {
                $number = $crntChar . $number;
                continue;
            } elseif (strlen($number) > 0) {
                $output .= $number;
                $number = '';
            }

            // handle the case of open and close brackets (flip them)
            if (mb_strpos($open_range . $close_range, $crntChar) !== false) {
                $output .= ($close_range . $open_range)[mb_strpos($open_range . $close_range, $crntChar)];
                continue;
            }

            // if it is an English char, then show it as it is
            if (ord($crntChar) < 128) {
                $output .= $crntChar;
                $nextChar = $crntChar;
                continue;
            }

//             if the current char is LAM followed by ALEF, use ALEF-LAM character, then step to the next char
            if (
                $crntChar == 'ل' && isset($nextChar)
                && (mb_strpos('آأإا', $nextChar) !== false)
            ) {
                $output = substr_replace($output, '', strrpos($output, $this->arGlyphs[$nextChar][1]) - 3, 8);
                if ($this->arGlyphs[$prevChar]['prevLink']) {
                    $output .= '&#x' . $this->arGlyphs[$crntChar . $nextChar][1] . ';';
                } else {
                    $output .= '&#x' . $this->arGlyphs[$crntChar . $nextChar][0] . ';';
                }
                continue;
            }

            // handle the case of HARAKAT
            if (mb_strpos($this->arGlyphsVowel, $crntChar) !== false) {
                if ($crntChar == 'ّ') {
                    if (mb_strpos($this->arGlyphsVowel, $chars[$i + 1]) !== false) {
                        // remove the HARAKA from output to merge it with SHADDA
                        $output = substr($output, 0, -8);

                        // check if the SHADDA & HARAKA in the middle of connected letters (form 3)
                        if (
                            ($prevChar && $this->arGlyphs[$prevChar]['prevLink'] == true) &&
                            ($nextChar && $this->arGlyphs[$nextChar]['nextLink'] == true)
                        ) {
                            $form = 3;
                        }

                        // handle the case of HARAKAT after SHADDA
                        switch ($chars[$i + 1]) {
                            case 'ٌ':
                                $output .= '&#xFC5E;';
                                break;
                            case 'ٍ':
                                $output .= '&#xFC5F;';
                                break;
                            case 'َ':
                                $output .= ($form == 3) ? '&#xFCF2;' : '&#xFC60;';
                                break;
                            case 'ُ':
                                $output .= ($form == 3) ? '&#xFCF3;' : '&#xFC61;';
                                break;
                            case 'ِ':
                                $output .= ($form == 3) ? '&#xFCF4;' : '&#xFC62;';
                                break;
                        }
                    } else {
                        $output .= '&#x0651;';
                    }
                } else {
                    switch ($crntChar) {
                        case 'ً':
                            $output .= '&#x064B;';
                            break;
                        case 'ٌ':
                            $output .= '&#x064C;';
                            break;
                        case 'ٍ':
                            $output .= '&#x064D;';
                            break;
                        case 'َ':
                            $output .= '&#x064E;';
                            break;
                        case 'ُ':
                            $output .= '&#x064F;';
                            break;
                        case 'ِ':
                            $output .= '&#x0650;';
                            break;
                        case 'ْ':
                            $output .= '&#x0652;';
                            break;
                    }
                }
                continue;
            }

            // check if it should connect to the prev char, then adjust the form value accordingly
            if ($prevChar && isset($this->arGlyphs[$prevChar]) && $this->arGlyphs[$prevChar]['prevLink'] == true) {
                $form++;
            }

            // check if it should connect to the next char, then adjust the form value accordingly
            if ($nextChar && isset($this->arGlyphs[$nextChar]) && $this->arGlyphs[$nextChar]['nextLink'] == true) {
                $form += 2;
            }

            // add the current char UTF-8 code to the output string
            $output .= '&#x' . $this->arGlyphs[$crntChar][$form] . ';';

            // next char will be the current one before loop (we are going backword to manage right-to-left presenting)
            $nextChar = $crntChar;
        }

        // from Arabic Presentation Forms-B, Range: FE70-FEFF,
        // file "UFE70.pdf" (in reversed order)
        // into Arabic Presentation Forms-A, Range: FB50-FDFF, file "UFB50.pdf"
        // Example: $output = strtr($output, array('&#xFEA0;&#xFEDF;' => '&#xFCC9;'));
        // Lam Jeem
        $output = $this->arGlyphsDecodeEntities($output, $exclude = array('&'));

        return $output;
    }

    public function utf8Glyphs($text)
    {
        $words = explode(" ", $text);

        $outLines = array();

        foreach ($words as $str) {
            // identify Arabic fragments in the line for glyphs
            $p = $this->findOffsets($str);

            // check if current line has any Arabic fragment
            if (count($p) > 0) {


                // block structure to save processed fragments
                $block = array();

                // if line does not start by Arabic, then save first non-Arabic fragment in block structure
                if ($p[0] != 0) {
                    $block[] = substr($str, 0, $p[0]);
                }

                // get the last Arabic fragment identifier
                $max = count($p);

                // for each Arabic fragment
                for ($i = 0; $i < $max; $i += 2) {
                    // do glyphs pre-processing and save the result in the block structure
                    $block[] = $this->arGlyphsPreConvert(substr($str, $p[$i], $p[$i + 1] - $p[$i]));

                    // if we still have another Arabic fragment
                    if ($i + 2 < $max) {
                        // get the in-between non-Arabic fragment as is and save it in the block structure
                        $block[] = substr($str, $p[$i + 1], $p[$i + 2] - $p[$i + 1]);
                    } elseif ($p[$i + 1] != strlen($str)) {
                        // else, the whole fragment starts after the last Arabic fragment
                        // until the end of the string will be save as is (non-Arabic) in the block structure
                        $block[] = substr($str, $p[$i + 1], strlen($str) - $p[$i + 1]);
                    }
                }

                // concatenate the whole string blocks
                $str = implode('', $block);
            }
            // add the processed string to the output lines array
            $outLines[] = $str;
        }
        $output = implode(' ', array_reverse($outLines));

        return $this->convertNumbers($output);
    }

    private function arGlyphsDecodeEntities($text, $exclude = array())
    {
        // Get all named HTML entities.
        $table = array_flip(get_html_translation_table(HTML_ENTITIES, ENT_COMPAT, 'UTF-8'));

        // Add apostrophe (XML)
        $table['&apos;'] = "'";

        $newtable = array_diff($table, $exclude);

        // Use a regexp to select all entities in one pass, to avoid decoding double-escaped entities twice.
        $text = preg_replace_callback('/&(#x?)?([A-Fa-f0-9]+);/u', function ($matches) use ($newtable, $exclude) {
            return $this->arGlyphsDecodeEntities2($matches[1], $matches[2], $matches[0], $newtable, $exclude);
        }, $text);

        return $text;
    }


    private function arGlyphsDecodeEntities2($prefix, $codepoint, $original, &$table, &$exclude)
    {
        // Named entity
        if (!$prefix) {
            if (isset($table[$original])) {
                return $table[$original];
            } else {
                return $original;
            }
        }

        // Hexadecimal numerical entity
        if ($prefix == '#x') {
            $codepoint = base_convert($codepoint, 16, 10);
        }

        $str = '';

        // Encode codepoint as UTF-8 bytes
        if ($codepoint < 0x80) {
            $str = chr((int)$codepoint);
        } elseif ($codepoint < 0x800) {
            $str = chr(0xC0 | ((int)$codepoint >> 6)) . chr(0x80 | ((int)$codepoint & 0x3F));
        } elseif ($codepoint < 0x10000) {
            $str = chr(0xE0 | ((int)$codepoint >> 12)) . chr(0x80 | (((int)$codepoint >> 6) & 0x3F)) .
                chr(0x80 | ((int)$codepoint & 0x3F));
        } elseif ($codepoint < 0x200000) {
            $str = chr(0xF0 | ((int)$codepoint >> 18)) . chr(0x80 | (((int)$codepoint >> 12) & 0x3F)) .
                chr(0x80 | (((int)$codepoint >> 6) & 0x3F)) . chr(0x80 | ((int)$codepoint & 0x3F));
        }

        // Check for excluded characters
        if (in_array($str, $exclude, true)) {
            return $original;
        } else {
            return $str;
        }
    }

    /**
     * Split the text to arrays by keeping the words as long as the number
     * of characters in each sentence is less than $maxCharInLine
     * @param string $text
     * @param int $maxCharInLine
     * @return array
     */
    public function split(string $text, int $maxCharInLine = 100): array
    {
        $length = mb_strlen($text);
        $texts = [];
        $i = 0;
        while ($length - $i > $maxCharInLine) {
            $temp = mb_substr($text, $i, $maxCharInLine);
            $pos = mb_strripos($temp, ' ');
            $texts[] = trim(mb_substr($text, $i, $pos));
            $i += $pos;
        }
        $texts[] = trim(mb_substr($text, $i, $length));
        return $texts;
    }

    /**
     * use string translate to convert numbers from Arabic to Hindu
     * @param string $output
     * @return string
     */
    public function convertNumbers(string $output): string
    {
        $arabic = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $hindu = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        return strtr($output, array_combine($arabic, $hindu));
    }
}
