<?php
namespace TYPO3\CMS\Fluid\Tests\Unit\Core\Compiler;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

/**
 * Test case
 */
class AbstractCompilerTemplateTest extends \TYPO3\CMS\Core\Tests\UnitTestCase
{
    /**
     * @test
     */
    public function resolveDefaultEncodingWillBeSetToUtf8IfNotSet()
    {
        $this->assertSame('UTF-8', \TYPO3\CMS\Fluid\Core\Compiler\AbstractCompiledTemplate::resolveDefaultEncoding());
    }

    /**
     * @test
     */
    public function isDefaultEncodingIsSetThanDefaultEncodingWillReturned()
    {
        $this->assertSame('ISO-8859-1', \TYPO3\CMS\Fluid\Tests\Unit\Core\Compiler\Fixtures\AbstractCompiledTemplate::resolveDefaultEncoding());
    }
}
