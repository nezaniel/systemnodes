<?php
namespace Nezaniel\SystemNodes\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
 *                                                                       */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Service\Context as ContentContext;

/**
 * @Flow\Scope("singleton")
 */
class ContentContextContainer
{

    /**
     * @var ContentContext
     */
    protected $contentContext;


    /**
     * @param ContentContext $contentContext
     * @return void
     */
    public function initializeContentContext(ContentContext $contentContext)
    {
        $this->contentContext = $contentContext;
    }

    /**
     * @return ContentContext
     */
    public function getContentContext()
    {
        if (!$this->contentContext) {
            $this->createContentContext();
        }

        return $this->contentContext;
    }

    /**
     * @return void
     */
    protected function createContentContext()
    {
        $this->contentContext = new ContentContext(
            'live',
            new \DateTime(),
            [],
            [],
            false,
            false,
            false
        );
    }
}
