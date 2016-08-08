<?php
namespace Nezaniel\SystemNodes\Eel\Helper;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
*                                                                        */
use Nezaniel\SystemNodes\Service\SystemNodeService;
use TYPO3\Eel\ProtectedContextAwareInterface;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * SystemNode helpers for Eel contexts
 */
class SystemNodeHelper implements ProtectedContextAwareInterface
{

    /**
     * @Flow\Inject
     * @var SystemNodeService
     */
    protected $systemNodeService;


    /**
     * @param array $identifier
     * @return NodeInterface[]
     */
    public function getSystemNodes(array $identifier)
    {
        return $this->systemNodeService->getSystemNodes($identifier);
    }

    /**
     * @param array $identifier
     * @return null|NodeInterface
     */
    public function getSystemNode(array $identifier)
    {
        return $this->systemNodeService->getSystemNode($identifier);
    }


    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName)
    {
        return true;
    }
}
