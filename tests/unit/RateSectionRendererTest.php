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
        $rates = ['1' => 1.05];
        $options = ['1' => 'Income', '2' => 'Expenses', '3' => 'COGS', '4' => 'Assets'];
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
        $rates = ['1' => 1.05];
        $options = ['1' => 'Expenses'];
        $result = RateSectionRenderer::render('category', 'Category Rates', 'Category', $rates, $options, 10, 'cat_page');
        
        $this->assertStringContainsString('1.05', $result);
    }

    /**
     * @testdox includes type in hidden inputs
     */
    public function testIncludesTypeInHiddenInputs(): void
    {
        $rates = [];
        $options = ['6000' => 'Utilities Expense'];
        $result = RateSectionRenderer::render('gl', 'GL Rates', 'GL Account', $rates, $options, 10, 'gl_page', true);
        
        $this->assertStringContainsString("name='type' value='gl'", $result);
    }

    /**
     * @testdox shows name for accounts
     */
    public function testShowsCodeWithRefForGLAccounts(): void
    {
        $rates = ['6000' => 1.10];
        $options = ['6000' => 'Utilities Expense'];
        $result = RateSectionRenderer::render('gl', 'GL Rates', 'GL Account', $rates, $options, 10, 'gl_page', true);
        
        $this->assertStringContainsString('Utilities Expense', $result);
    }

    /**
     * @testdox renders all options in select
     */
    public function testRendersAllOptionsInSelect(): void
    {
        $rates = [];
        $options = ['1' => 'Income', '2' => 'COGS', '3' => 'Expenses', '4' => 'Assets'];
        $result = RateSectionRenderer::render('category', 'Category Rates', 'Category', $rates, $options, 10, 'cat_page');
        
        $this->assertStringContainsString('Income', $result);
        $this->assertStringContainsString('COGS', $result);
        $this->assertStringContainsString('Expenses', $result);
        $this->assertStringContainsString('Assets', $result);
    }

    /**
     * @testdox renderTypeCache shows all types with resolved rates
     */
    public function testRenderTypeCacheShowsAllTypesWithNames(): void
    {
        // Pass id => name mapping, rates keyed by typeId (string) per new architecture
        $rates = ['resolved_types' => ['1' => 1.05, '2' => 1.10], 'global' => 1.02];
        $allTypes = ['1' => 'Utilities', '2' => 'Services', '3' => 'Income'];
        $result = RateSectionRenderer::renderTypeCache($rates, $allTypes);
        
        $this->assertStringContainsString('Utilities', $result);
        $this->assertStringContainsString('Services', $result);
        $this->assertStringContainsString('Income', $result);
        $this->assertStringContainsString('1.05', $result);
        $this->assertStringContainsString('1.1', $result);
        $this->assertStringContainsString('1.02', $result);
    }
}