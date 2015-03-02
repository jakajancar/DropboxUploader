<?php
/**
 * This file is part of Dropbox Uploader Phpunit testsuite
 *
 * Copyright (c) 2014 hakre <http://hakre.wordpress.com/>
 *
 * See COPYING
 */

/**
 * Class RequirementsTest
 */
class RequirementsTest extends BaseTestCase
{
    /**
     * preg_match named subpattern and duplicate names support
     *
     * @test
     * @see DropboxUploader::extractTokenFromLoginForm()
     */
    public function testNamedSubpatterns()
    {
        $actual = preg_match('~\w?:(?P<name>[a-z]+)~', '~A:foo~', $matches);

        $this->assertEquals(true, $actual);
        $this->assertArrayHasKey('name', $matches);
        $this->assertEquals('foo', $matches['name']);

        $actual = preg_match('~(?J)\W?:(?P<name>[a-z]+)|\w?:(?P<name>[a-z]+)~', '~B:bar~', $matches);

        $this->assertEquals(true, $actual);
        $this->assertArrayHasKey('name', $matches);
        $this->assertEquals('bar', $matches['name']);
    }
}
