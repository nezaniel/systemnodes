<?php
namespace Nezaniel\SystemNodes\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
 *                                                                       */

use TYPO3\Flow\Annotations as Flow;
use TYPO3\Neos\Domain\Repository\DomainRepository;
use TYPO3\Neos\Domain\Repository\SiteRepository;
use TYPO3\Neos\Domain\Service\ContentContext;

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
     * @Flow\Inject
     * @var DomainRepository
     */
    protected $domainRepository;

    /**
     * @Flow\Inject
     * @var SiteRepository
     */
    protected $siteRepository;


    /**
     * @param ContentContext $contentContext
     * @return void
     */
    public function initializeContentContext(ContentContext $contentContext)
    {
        if ($contentContext->getCurrentSite()) {
            $this->contentContext = $contentContext;
        }
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
        $domain = $this->domainRepository->findOneByActiveRequest();

        $this->contentContext = new ContentContext(
            'live',
            new \DateTime(),
            [],
            [],
            false,
            false,
            false,
            $domain ? $domain->getSite() : $this->siteRepository->findFirstOnline(),
            $domain
        );
    }
}
