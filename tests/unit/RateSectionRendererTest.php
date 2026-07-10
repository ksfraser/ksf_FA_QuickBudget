<?php
/**
 * RateSectionRendererTest
 *
 * Tests for rate section rendering.
 *
 * @testdox RateSectionRenderer
 */
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class RateSectionRendererTest extends TestCase
{
    private $renderer;

    protected function setUp(): void
    {
        $this->renderer = new RateSectionRenderer();
    }

    /**
     * @testdox renders table before form
     */
    public function testRendersTableBeforeForm(): void
    {
        $rates = ['Expenses' => 1.05];
        $options = [1 => 'Expenses', 2 => 'Income'];
        $result = RateSectionRenderer::render('category', 'Category Rates', 'Category', $rates, $options, 10, 'cat_page');
        
        $tablePos = strpos($result, '<table');
        $formPos = strpos($result, '<form');
        
        $this->assertLessThan($formPos, $tablePos, 'Table should appear before form');
        $this->assertGreaterThan($tablePos, strpos($result, '</table>'), 'Table should be closed');
        $this->assertGreaterThan($formPos, strpos($result, '</form>'), 'Form should be closed');
    }

    /**
     * @testdox includes rate values in output
     */
    public function testIncludesRateValues(): void
    {
        $rates = ['Expenses' => 1.05];
        $options = [1 => 'Expenses', 2 => 'Income'];
        $result = RateSectionRenderer::render('category', 'Category Rates', 'Category', $rates, $options, 10, 'cat_page');
        
        $this->assertStringContainsString('1.05', $result);
    }

    /**
     * @testdox includes type in hidden inputs
     */
    public function testIncludesTypeInHiddenInputs(): void
    {
        $rates = [];
        $options = [1 => 'Expenses'];
        $result = RateSectionRenderer::render('gl', 'GL Rates', 'GL Account', $rates, $options, 10, 'gl_page', true);
        
        $this->assertStringContainsString("name='type' value='gl'", $result);
    }

    /**
     * @testdox shows code with ref for GL accounts
     */
    public function testShowsCodeWithRefForGLAccounts(): void
    {
        $rates = ['6000' => 1.10];
        $options = ['6000' => 'Utilities Expense'];
        $result = RateSectionRenderer::render('gl', 'GL Rates', 'GL Account', $rates, $options, 10, 'gl_page', true);
        
        $this->assertStringContainsString('6000 - Utilities Expense', $result);
    }

    /**
     * @testdox renders all options in select
     */
    public function testRendersAllOptionsInSelect(): void
    {
        $rates = [];
        $options = [1 => 'Income', 2 => 'COGS', 3 => 'Expenses', 4 => 'Assets'];
        $result = RateSectionRenderer::render('category', 'Category Rates', 'Category', $rates, $options, 10, 'cat_page');
        
        $this->assertStringContainsString('Income', $result);
        $this->assertStringContainsString('COGS', $result);
        $this->assertStringContainsString('Expenses', $result);
        $this->assertStringContainsString('Assets', $result);
    }
}