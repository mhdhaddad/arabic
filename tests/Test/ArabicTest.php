<?php

namespace Test;

use Mhdhaddad\Arabic\Arabic;
use PHPUnit\Framework\TestCase;

/**
 * @covers  Arabic
 */
class ArabicTest extends TestCase
{
    public function test_find_arabic_text()
    {
        $arabic = new Arabic();
        $originalParagraph = 'هذا النص العربي هنا 2022';

        $offsets = $arabic->findOffsets($originalParagraph);

        $arabicExpected[] = $originalParagraph;
        $this->checkStrings($originalParagraph, $offsets, $arabicExpected);
        $this->assertEquals([0, 40], $offsets);
    }

    public function test_find_arabic_text_2()
    {
        $arabic = new Arabic();
        $originalParagraph = 'هذا النص  text العربي هنا 2022';

        $offsets = $arabic->findOffsets($originalParagraph);

        $arabicExpected[] = 'هذا النص';
        $arabicExpected[] = 'العربي هنا 2022';
        $this->checkStrings($originalParagraph, $offsets, $arabicExpected);
        $expectedOffsets = [0, 15, 22, 46];
        $this->assertEquals($expectedOffsets, $offsets);
    }

    public function test_find_arabic_text_3()
    {
        $arabic = new Arabic();
        $originalParagraph = "استخدامكم لأرشيف (مكتبة) الموسوعة السياسيَّة - Political Encyclopedia يعني موافقتك على شروط وأحكام الاستخدام المتاحة على الموقع";
        $arabicExpected[] = 'استخدامكم لأرشيف (مكتبة) الموسوعة السياسيَّة -';
        $arabicExpected[] = 'يعني موافقتك على شروط وأحكام الاستخدام المتاحة على الموقع';

        $offsets = $arabic->findOffsets($originalParagraph);

        $this->checkStrings($originalParagraph, $offsets, $arabicExpected);
        $expectedOffsets = [0, 84, 108, 214];
        $this->assertEquals($expectedOffsets, $offsets);
    }

    public function test_find_arabic_text_4()
    {
        $arabic = new Arabic();
        $originalParagraph[] = "الموسوعة السياسيّة هي مبادرة أكاديمية غير هادفة للربح، تساعد الباحثين والطلاب على الوصول واستخدام وبناء مجموعات أوسع من المحتوى العلمي العربي في مجال علم السياسة واستخدامها في الأرشيف الرقمي الموثوق به لإغناء المحتوى العربي على الإنترنت.  لمزيد من المعلومات حول الموسوعة السياسيّة - ";
        $originalParagraph[] = "Political Encyclopedia";
        $originalParagraph[] = "، يرجى التواصل على.";
        $originalParagraph[] = "info@political-encyclopedia.org.";
        $originalParagraph = implode(' ', $originalParagraph);

        $offsets = $arabic->findOffsets($originalParagraph);

        $arabicExpected[] = 'استخدامكم لأرشيف مكتبة الموسوعة السياسيِّة -';
        $arabicExpected[] = 'يعني موافقتك على شروط وأحكام';
        $arabicExpected[] = 'الموسوعة السياسيّة هي مبادرة أكاديمية غير هادفة للربح، تساعد الباحثين والطلاب على الوصول واستخدام وبناء مجموعات أوسع من المحتوى العلمي العربي في مجال علم السياسة واستخدامها في الأرشيف الرقمي الموثوق به لإغناء المحتوى العربي على الإنترنت.  لمزيد من المعلومات حول الموسوعة السياسيّة -';
        $arabicExpected[] = '، يرجى التواصل على.';
        $this->checkStrings($originalParagraph, $offsets, $arabicExpected);
        $expectedOffsets = [0, 519, 544, 578];
        $this->assertEquals($expectedOffsets, $offsets);
    }

    public function test_find_arabic_text_5()
    {
        $arabic = new Arabic();
        $originalParagraph = "يرجى التواصل على";
        $resultParagraph = $arabic->utf8Glyphs($originalParagraph);
        $this->assertEquals('ﻰﻠﻋ ﻞﺻاﻮﺘﻟا ﻰﺟﺮﻳ', $resultParagraph);
        $originalParagraph = "استخدامكم لأرشيف مكتبة الموسوعة السياسيِّة -";
        $resultParagraph = $arabic->utf8Glyphs($originalParagraph);
        $this->assertEquals('- ﺔﳴﻴﺳﺎﻴﺴﻟا ﺔﻋﻮﺳﻮﻤﻟا ﺔﺒﺘﻜﻣ ﻒﻴﺷرﻷ ﻢﻜﻣاﺪﺨﺘﺳا', $resultParagraph);
    }

    /**
     * @param array $offsets
     * @param string $originalParagraph
     * @param array $arabicExpected
     */
    public function checkStrings(string $originalParagraph, array $offsets, array $arabicExpected)
    {
        for ($i = count($offsets) - 1; $i > 0; $i -= 2) {
            $offset = $offsets[$i - 1];
            $length = $offsets[$i] - $offset;
            $arabicSentence = substr($originalParagraph, $offset, $length);
            $this->assertEquals(array_pop($arabicExpected), $arabicSentence);
        }
    }
}
