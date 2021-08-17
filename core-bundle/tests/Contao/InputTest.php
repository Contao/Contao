<?php

/*
 * This file is part of Contao.
 *
 * (c) Leo Feyer
 *
 * @license LGPL-3.0-or-later
 */

namespace Contao\CoreBundle\Tests\Contao;

use Contao\Config;
use Contao\CoreBundle\Tests\TestCase;
use Contao\Input;

/**
 * Tests the Input class.
 *
 * @group contao3
 */
class InputTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $GLOBALS['TL_CONFIG'] = [];

        include __DIR__.'/../../src/Resources/contao/config/default.php';

        $GLOBALS['TL_CONFIG']['allowedTags'] = (isset($GLOBALS['TL_CONFIG']['allowedTags']) ? $GLOBALS['TL_CONFIG']['allowedTags'] : '').'<use>';
        $GLOBALS['TL_CONFIG']['allowedAttributes'] = serialize(
            array_merge(
                unserialize($GLOBALS['TL_CONFIG']['allowedAttributes']),
                [['key' => 'use', 'value' => 'xlink:href']]
            )
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        unset($GLOBALS['TL_CONFIG']);
    }

    /**
     * Tests stripping tags and attributes.
     *
     * @dataProvider stripTagsProvider
     *
     * @param string $source
     * @param string $expected
     */
    public function testStripTags($source, $expected)
    {
        $allowedTags = Config::get('allowedTags');
        $allowedAttributes = Config::get('allowedAttributes');

        $this->assertSame($expected, Input::stripTags($source, $allowedTags, $allowedAttributes));
    }

    /**
     * Provides the data for the testStripTags() method.
     *
     * @return array
     */
    public function stripTagsProvider()
    {
        return [
            'Encodes tags' => [
                'Text <with> tags',
                'Text &lt;with&#62; tags',
            ],
            'Keeps allowed tags' => [
                'Text <with> <span> tags',
                'Text &lt;with&#62; <span> tags',
            ],
            'Removes attributes' => [
                'foo <span onerror=alert(1)> bar',
                'foo <span> bar',
            ],
            'Keeps allowed attributes' => [
                'foo <span onerror="foo" title="baz" href="bar"> bar',
                'foo <span title="baz"> bar',
            ],
            'Reformats attributes' => [
                "<span \n \t title = \nwith-spaces class\n=' with \" and &#039; quotes' lang \t =\"with &quot; and ' quotes \t \n \" data-boolean-flag data-int = 0>",
                "<span title=\"with-spaces\" class=\" with &quot; and &#039; quotes\" lang=\"with &quot; and &#039; quotes \t \n \" data-boolean-flag=\"\" data-int=\"0\">",
            ],
            'Encodes insert tags in attributes' => [
                '<a href = {{br}} title = {{br}}>',
                '<a href="{{br|urlattr}}" title="{{br|attr}}">',
            ],
            'Encodes nested insert tags' => [
                '<a href="{{email_url::{{link_url::1}}}}">',
                '<a href="{{email_url::{{link_url::1|urlattr}}|urlattr}}">',
            ],
            'Does not allow colon in URLs' => [
                '<a href="ja{{noop}}vascript:alert(1)">',
                '<a href="ja{{noop|urlattr}}vascript%3Aalert(1)">',
            ],
            'Allows colon for absolute URLs' => [
                '<a href="http://example.com"><a href="https://example.com"><a href="mailto:john@example.com"><a href="tel:0123456789">',
                '<a href="http://example.com"><a href="https://example.com"><a href="mailto:john@example.com"><a href="tel:0123456789">',
            ],
            'Does not allow colon in URLs insert tags' => [
                '<a href="{{email_url::javascript:alert(1)|attr}}">',
                '<a href="{{email_url::javascript:alert(1)|urlattr}}">',
            ],
            'Does not get tricked by stripping null escapes' => [
                '<img src="foo{{bar}\\0}baz">',
                '<img src="foo{{bar&#125;&#92;0&#125;baz">',
            ],
            'Does not get tricked by stripping insert tags' => [
                '<img src="foo{{bar}{{noop}}}baz">',
                '<img src="foo{{bar&#125;{{noop|urlattr}}&#125;baz">',
            ],
            'Do not destroy JSON attributes' => [
                '<span data-myjson=\'{"foo":{"bar":"baz"}}\'>',
                '<span data-myjson="{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;&#125;&#125;">',
            ],
            'Do not destroy nested JSON attributes' => [
                '<span data-myjson=\'[{"foo":{"bar":"baz"}},12.3,"string"]\'>',
                '<span data-myjson="[{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;&#125;&#125;,12.3,&quot;string&quot;]">',
            ],
            'Do not destroy quoted JSON attributes' => [
                '<span data-myjson="{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;}}">',
                '<span data-myjson="{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;&#125;&#125;">',
            ],
            'Do not destroy nested quoted JSON attributes' => [
                '<span data-myjson="[{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;}},12.3,&quot;string&quot;]">',
                '<span data-myjson="[{&quot;foo&quot;:{&quot;bar&quot;:&quot;baz&quot;&#125;&#125;,12.3,&quot;string&quot;]">',
            ],
            'Trick insert tag detection with JSON' => [
                '<span data-myjson=\'{"foo":{"{{bar::":"baz"}}\'>',
                '<span data-myjson="{&quot;foo&quot;:{&quot;{{bar::&quot;:&quot;baz&quot;|attr}}">',
            ],
            'Allows namespaced attributes' => [
                '<use xlink:href="http://example.com">',
                '<use xlink:href="http://example.com">',
            ],
            'Does not allow colon in namespaced URL attributes' => [
                '<use xlink:href="ja{{noop}}vascript:alert(1)">',
                '<use xlink:href="ja{{noop|urlattr}}vascript%3Aalert(1)">',
            ],
            'Allows for comments' => [
                '<!-- my comment --> <span non-allowed="should be removed">',
                '<!-- my comment --> <span>',
            ],
            'Encodes comments contents' => [
                '<!-- my comment <script>alert(1)</script> --> <span non-allowed="should be removed">',
                '<!-- my comment &lt;script&#62;alert(1)&lt;/script&#62; --> <span>',
            ],
            'Does not encode allowed elements in comments' => [
                '<!-- my comment <span non-allowed="should be removed" title="--&#62;"> --> <span non-allowed="should be removed">',
                '<!-- my comment <span title="--&#62;"> --> <span>',
            ],
            'Normalize short comments' => [
                '<!--> a <!---> b <!----> c <!-----> d',
                '<!----> a <!----> b <!----> c <!-----> d',
            ],
            'Nested comments' => [
                '<!-- a <!-- b --> c --> d <!-- a> <!-- b> --> c> --> d>',
                '<!-- a &#60;!-- b --> c --&#62; d <!-- a&#62; &#60;!-- b&#62; --> c&#62; --&#62; d&#62;',
            ],
            'Style tag' => [
                '<style not-allowed="x" media="(min-width: 10px)"> body { background: #fff; color: rgba(1, 2, 3, 0.5) } #header::after { content: "> <!--"; } @media print { #header { display: none; }}</style>>>',
                '<style media="(min-width: 10px)"> body { background: #fff; color: rgba(1, 2, 3, 0.5) } #header::after { content: "> <!--"; } @media print { #header { display: none; }}</style>&#62;&#62;',
            ],
            'Style tag with comment' => [
                '<style not-allowed="x" media="(min-width: 10px)"><!-- body { background: #fff; color: rgba(1, 2, 3, 0.5) } #header::after { content: "> <!--"; } @media print { #header { display: none; }}--></style>>>',
                '<style media="(min-width: 10px)"><!-- body { background: #fff; color: rgba(1, 2, 3, 0.5) } #header::after { content: "> <!--"; } @media print { #header { display: none; }}--></style>&#62;&#62;',
            ],
            'Style nested in comment' => [
                '<!-- <style> --> content: ""; <span non-allowed="x"> <style> --> content: ""; <span non-allowed="x">',
                '<!-- <style> --> content: &#34;&#34;; <span> <style> --> content: ""; <span non-allowed="x">',
            ],
            [
                '<form action="javascript:alert(document.domain)"><input type="submit" value="XSS" /></form>',
                '<form><input></form>',
            ],
            [
                '<img src onerror=alert(document.domain)>',
                '<img src="">',
            ],
            [
                '<SCRIPT SRC=http://xss.rocks/xss.js></SCRIPT>',
                '&lt;SCRIPT SRC&#61;http://xss.rocks/xss.js&#62;&lt;/SCRIPT&#62;',
            ],
            [
                'javascript:/*--></title></style></textarea></script></xmp><svg/onload=\'+/"/+/onmouseover=1/+/[*/[]/+alert(1)//\'>',
                'javascript:/*--&#62;&lt;/title&#62;</style></textarea>&lt;/script&#62;&lt;/xmp&#62;&lt;svg/onload&#61;&#39;+/&#34;/+/onmouseover&#61;1/+/[*/[]/+alert(1)//&#39;&#62;',
            ],
            [
                '<IMG SRC="javascript:alert(\'XSS\');">',
                '<img src="javascript%3Aalert(&#039;XSS&#039;);">',
            ],
            [
                '<IMG SRC=JaVaScRiPt:alert(\'XSS\')>',
                '<img src="JaVaScRiPt%3Aalert(&#039;XSS&#039;)">',
            ],
            [
                '<IMG SRC=javascript:alert(&quot;XSS&quot;)>',
                '<img src="javascript%3Aalert(&quot;XSS&quot;)">',
            ],
            [
                '<IMG SRC=`javascript:alert("RSnake says, \'XSS\'")`>',
                '<img src="`javascript%3Aalert(&quot;RSnake">',
            ],
            [
                '\<a onmouseover="alert(document.cookie)"\>xxs link\</a\>',
                '\<a>xxs link\&lt;/a\&#62;',
            ],
            [
                '\<a onmouseover=alert(document.cookie)\>xxs link\</a\>',
                '\<a>xxs link\&lt;/a\&#62;',
            ],
            [
                '<IMG """><SCRIPT>alert("XSS")</SCRIPT>"\>',
                '<img>',
            ],
            [
                '<img src=x onerror="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041">',
                '<img src="x">',
            ],
            [
                '<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>',
                '<img src="&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;%3A&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;">',
            ],
            [
                '<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>',
                '<img src="&amp;#0000106&amp;#0000097&amp;#0000118&amp;#0000097&amp;#0000115&amp;#0000099&amp;#0000114&amp;#0000105&amp;#0000112&amp;#0000116&amp;#0000058&amp;#0000097&amp;#0000108&amp;#0000101&amp;#0000114&amp;#0000116&amp;#0000040&amp;#0000039&amp;#0000088&amp;#0000083&amp;#0000083&amp;#0000039&amp;#0000041">',
            ],
            [
                '<IMG SRC="jav&#x0A;ascript:alert(\'XSS\');">',
                '<img src="jav&#x0A;ascript%3Aalert(&#039;XSS&#039;);">',
            ],
            [
                '<IMG SRC=" &#14; javascript:alert(\'XSS\');">',
                '<img src=" &#14; javascript%3Aalert(&#039;XSS&#039;);">',
            ],
            [
                '<SCRIPT/SRC="http://xss.rocks/xss.js"></SCRIPT>',
                '&lt;SCRIPT/SRC&#61;&#34;http://xss.rocks/xss.js&#34;&#62;&lt;/SCRIPT&#62;',
            ],
            [
                '<BODY onload!#$%&()*~+-_.,:;?@[/|\]^`=alert("XSS")>',
                '&lt;BODY onload!#$%&()*~+-_.,:;?@[/|\]^`&#61;alert(&#34;XSS&#34;)&#62;',
            ],
            [
                '<<SCRIPT>alert("XSS");//\<</SCRIPT>',
                '&lt;&lt;SCRIPT&#62;alert(&#34;XSS&#34;);//\&lt;&lt;/SCRIPT&#62;',
            ],
            [
                '<IMG SRC="`(\'XSS\')"`',
                '',
            ],
            [
                '</TITLE><SCRIPT>alert("XSS");</SCRIPT>',
                '&lt;/TITLE&#62;&lt;SCRIPT&#62;alert(&#34;XSS&#34;);&lt;/SCRIPT&#62;',
            ],
            [
                '<INPUT TYPE="IMAGE" SRC="javascript:alert(\'XSS\');">',
                '<input>',
            ],
            [
                '<BODY BACKGROUND="javascript:alert(\'XSS\')">',
                '&lt;BODY BACKGROUND&#61;&#34;javascript:alert(&#39;XSS&#39;)&#34;&#62;',
            ],
            [
                '<IMG DYNSRC="javascript:alert(\'XSS\')">',
                '<img>',
            ],
            [
                '<IMG LOWSRC="javascript:alert(\'XSS\')">',
                '<img>',
            ],
            [
                '<svg/onload=alert(\'XSS\')>',
                '&lt;svg/onload&#61;alert(&#39;XSS&#39;)&#62;',
            ],
            [
                '<LINK REL="stylesheet" HREF="javascript:alert(\'XSS\');">',
                '&lt;LINK REL&#61;&#34;stylesheet&#34; HREF&#61;&#34;javascript:alert(&#39;XSS&#39;);&#34;&#62;',
            ],
        ];
    }

    /**
     * @dataProvider stripTagsNoTagsAllowedProvider
     */
    public function testStripTagsNoTagsAllowed($source, $expected)
    {
        $this->assertSame($expected, Input::stripTags($source));
    }

    public function stripTagsNoTagsAllowedProvider()
    {
        return [
            'Encodes tags' => [
                'Text <with> tags',
                'Text &lt;with> tags',
            ],
            'Does not encode other special characters' => [
                'xLmpwZw==',
                'xLmpwZw==',
            ],
        ];
    }

    public function testStripTagsAllAttributesAllowed()
    {
        $html = '<dIv class=gets-normalized bar-foo-something = \'keep\'><spAN class=gets-normalized bar-foo-something = \'keep\'>foo</SPan></DiV>';
        $expected = '<div class="gets-normalized" bar-foo-something="keep"><span>foo</span></div>';

        $this->assertSame($expected, Input::stripTags($html, '<div><span>', serialize([['key' => 'div', 'value' => '*']])));
    }

    public function testStripTagsAllAttributesAllowedAllTags()
    {
        $html = '<spAN class=no-normalization-happens-if-all-is-allowed>foo</SPan>';

        $this->assertSame($html, Input::stripTags($html, '<span>', serialize([['key' => '*', 'value' => '*']])));
    }

    public function testStripTagsNoAttributesAllowed()
    {
        $html = '<dIv class=gets-normalized bar-foo-something = \'keep\'><spAN class=gets-normalized bar-foo-something = \'keep\'>foo</SPan></DiV><notallowed></notallowed>';
        $expected = '<div><span>foo</span></div>&lt;notallowed&#62;&lt;/notallowed&#62;';

        $this->assertSame($expected, Input::stripTags($html, '<div><span>', serialize([['key' => '', 'value' => '']])));
        $this->assertSame($expected, Input::stripTags($html, '<div><span>', serialize([[]])));
        $this->assertSame($expected, Input::stripTags($html, '<div><span>', serialize([])));
        $this->assertSame($expected, Input::stripTags($html, '<div><span>', serialize(null)));
        $this->assertSame($expected, Input::stripTags($html, '<div><span>', ''));
        $this->assertSame($expected, Input::stripTags($html, '<div><span>', null));
    }

    public function testStripTagsScriptAllowed()
    {
        $this->assertSame(
            '<script>alert(foo > bar);</script>foo &#62; bar',
            Input::stripTags('<script>alert(foo > bar);</script>foo > bar', '<div><span><script>', '')
        );

        $this->assertSame(
            '<script><!-- alert(foo > bar); --></script>foo &#62; bar',
            Input::stripTags('<script><!-- alert(foo > bar); --></script>foo > bar', '<div><span><script>', '')
        );

        $this->assertSame(
            '<script><!-- alert(foo > bar); </script>foo &#62; bar',
            Input::stripTags('<scrIpt type="VBScript"><!-- alert(foo > bar); </SCRiPT >foo > bar', '<div><span><script>', '')
        );
    }

    /**
     * @group legacy
     *
     * @expectedDeprecation Using Input::stripTags() with $strAllowedTags but without $strAllowedAttributes has been deprecated%s
     */
    public function testStripTagsMissingAttributesParameter()
    {
        $html = '<dIv class=gets-normalized bar-foo-something = \'keep\'><spAN notallowed="x" class=gets-normalized bar-foo-something = \'keep\'>foo</SPan></DiV><notallowed></notallowed>';
        $expected = '<div class="gets-normalized"><span class="gets-normalized">foo</span></div>&lt;notallowed&#62;&lt;/notallowed&#62;';

        $this->assertSame($expected, Input::stripTags($html, '<div><span>'));
    }
}
