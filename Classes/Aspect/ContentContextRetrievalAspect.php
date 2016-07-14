<?php
namespace Nezaniel\SystemNodes\Aspect;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
 *                                                                       */

use Nezaniel\SystemNodes\Service\ContentContextContainer;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Aop\JoinPointInterface;

/**
 * @Flow\Scope("singleton")
 * @Flow\Aspect
 */
class ContentContextRetrievalAspect
{

    /**
     * @Flow\Inject
     * @var ContentContextContainer
     */
    protected $contentContextContainer;


    /**
     * @Flow\AfterReturning("method(TYPO3\TYPO3CR\Domain\Service\Context->__construct())")
     * @param \TYPO3\Flow\Aop\JoinPointInterface $joinPoint The current join point
     * @return void
     */
    public function retrieveContentContext(JoinPointInterface $joinPoint)
    {
        $this->contentContextContainer->initializeContentContext($joinPoint->getProxy());
    }

}
