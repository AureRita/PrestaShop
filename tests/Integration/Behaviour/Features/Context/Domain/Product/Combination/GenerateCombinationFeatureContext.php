<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Tests\Integration\Behaviour\Features\Context\Domain\Product\Combination;

use Behat\Gherkin\Node\TableNode;
use PHPUnit\Framework\Assert;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\GenerateProductCombinationsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use Product;
use Tests\Integration\Behaviour\Features\Context\Util\PrimitiveUtils;

class GenerateCombinationFeatureContext extends AbstractCombinationFeatureContext
{
    /**
     * @When I generate combinations for product :productReference using following attributes:
     *
     * @param string $productReference
     * @param TableNode $table
     */
    public function generateCombinationsForDefaultShop(string $productReference, TableNode $table): void
    {
        $this->generateCombinations($productReference, $table, $this->getDefaultShopId());
    }

    /**
     * @When I generate combinations in shop ":shopReference" for product :productReference using following attributes:
     *
     * @param string $shopReference
     * @param string $productReference
     * @param TableNode $table
     */
    public function generateCombinationsForShop(string $shopReference, string $productReference, TableNode $table): void
    {
        $this->generateCombinations(
            $productReference,
            $table,
            $this->getSharedStorage()->get($shopReference)
        );
    }

    /**
     * @Then combination :combinationReference should be named :combinationName
     *
     * @param string $combinationReference
     * @param string $combinationName
     */
    public function assertCombinationName(string $combinationReference, string $combinationName): void
    {
        $combinationForEditing = $this->getCombinationForEditing($combinationReference);

        Assert::assertSame(
            $combinationName,
            $combinationForEditing->getName(),
            sprintf(
                'Unexpected name %s, expected %s',
                $combinationForEditing->getName(),
                $combinationName
            )
        );
    }

    /**
     * @Then product :productReference default combination should be :combinationReference
     *
     * @param string $productReference
     * @param string $combinationReference
     */
    public function assertDefaultCombinationForDefaultShop(string $productReference, string $combinationReference): void
    {
        $this->assertDefaultCombination($productReference, $combinationReference);
    }

    /**
     * @Then product :productReference default combination for shop :shopReference should be :combinationReference
     *
     * @param string $productReference
     * @param string $shopReference
     * @param string $combinationReference
     */
    public function assertDefaultCombinationForShop(
        string $productReference,
        string $shopReference,
        string $combinationReference
    ): void {
        $this->assertDefaultCombination($productReference, $combinationReference, $shopReference);
    }

    /**
     * @param string $productReference
     * @param string $combinationReference
     * @param string|null $shopReference
     */
    private function assertDefaultCombination(
        string $productReference,
        string $combinationReference,
        ?string $shopReference = null
    ) {
        $combinationId = $this->getSharedStorage()->get($combinationReference);

        $this->assertCachedDefaultCombinationId(
            $productReference,
            $combinationId,
            $shopReference
        );

        Assert::assertTrue(
            $this->getCombinationForEditing($combinationReference, $shopReference)->isDefault(),
            sprintf('Unexpected default combination in CombinationForEditing for "%s"', $combinationReference)
        );
    }

    /**
     * @Given product :productReference should not have a default combination
     * @Given product :productReference does not have a default combination
     *
     * @param string $productReference
     */
    public function assertProductHasNoCachedDefaultCombination(string $productReference): void
    {
        $this->assertCachedDefaultCombinationId($productReference, 0);
    }

    /**
     * @Given product :productReference should not have a default combination for shop ":shopReference"
     *
     * @param string $productReference
     */
    public function assertProductHasNoCachedDefaultCombinationForShop(string $productReference, string $shopReference): void
    {
        $this->assertCachedDefaultCombinationId($productReference, 0, $shopReference);
    }

    /**
     * @param string $productReference
     * @param int $combinationId
     */
    private function assertCachedDefaultCombinationId(string $productReference, int $combinationId, ?string $shopReference = null): void
    {
        $product = new Product(
            $this->getSharedStorage()->get($productReference),
            false,
            null,
            $shopReference ? $this->getSharedStorage()->get($shopReference) : $this->getDefaultShopId()
        );

        Assert::assertEquals(
            $combinationId,
            (int) $product->cache_default_attribute,
            'Unexpected cached product default combination'
        );
    }

    /**
     * @Given product :productReference should not have a default combination for shop :shopReference
     *
     * @param string $productReference
     * @param int $combinationId
     * @param string $shopReference
     */
    private function assertCachedDefaultCombinationForShop(
        string $productReference,
        int $combinationId,
        string $shopReference
    ): void {
        $product = new Product(
            $this->getSharedStorage()->get($productReference),
            false,
            null,
            $this->getSharedStorage()->get($shopReference)
        );

        Assert::assertEquals(
            $combinationId,
            (int) $product->cache_default_attribute,
            'Unexpected cached product default combination'
        );
    }

    /**
     * @param array $groupedReferences
     *
     * @return array
     */
    private function parseGroupedAttributeIds(array $groupedReferences): array
    {
        $groupedAttributeIds = [];
        foreach ($groupedReferences as $attributeGroupReference => $attributeReferences) {
            $attributeIds = [];
            foreach (PrimitiveUtils::castStringArrayIntoArray($attributeReferences) as $attributeReference) {
                $attributeIds[] = $this->getSharedStorage()->get($attributeReference);
            }

            $groupedAttributeIds[$this->getSharedStorage()->get($attributeGroupReference)] = $attributeIds;
        }

        return $groupedAttributeIds;
    }

    /**
     * @param string $productReference
     * @param TableNode $table
     * @param int $shopId
     */
    private function generateCombinations(string $productReference, TableNode $table, int $shopId): void
    {
        $tableData = $table->getRowsHash();
        $groupedAttributeIds = $this->parseGroupedAttributeIds($tableData);

        try {
            $this->getCommandBus()->handle(new GenerateProductCombinationsCommand(
                $this->getSharedStorage()->get($productReference),
                $groupedAttributeIds,
                $shopId
            ));
        } catch (InvalidProductTypeException $e) {
            $this->setLastException($e);
        }
    }
}
