<?php
namespace Nezaniel\SystemNodes\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
*                                                                        */
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Neos\Domain\Service\ContentContextFactory;
use TYPO3\TYPO3CR\Domain\Model\NodeInterface;

/**
 * The service to find specific system-relevant nodes
 *
 * @Flow\Scope("singleton")
 */
class SystemNodeService
{
    /**
     * @Flow\Inject
     * @var ContentContextContainer
     */
    protected $contentContextContainer;

    /**
     * @Flow\Inject
     * @var ContentContextFactory
     */
    protected $contentContextFactory;

    /**
     * @Flow\InjectConfiguration(path="contentContext.rootNodePath")
     * @var string
     */
    protected $rootNodePath;


    /**
     * @var array
     */
    protected $systemNodeIdentifiers = [];

    /**
     * @var array|NodeInterface[]
     */
    protected $systemNodes = [];

    /**
     * @var VariableFrontend
     */
    protected $cache;


    /**
     * @param VariableFrontend $cache
     */
    public function setCache(VariableFrontend $cache)
    {
        $this->cache = $cache;
    }


    /**
     * Initialize the object and load caches
     */
    public function initializeObject()
    {
        if (!$this->cache->get('systemNodeIdentifiers')) {
            $this->initializeSystemNodes();
        } else {
            foreach ($this->cache->get('systemNodeIdentifiers') as $key => $identifier) {
                $this->systemNodeIdentifiers[$key] = $identifier;
            }
        }
    }

    /**
     * @return void
     */
    protected function initializeSystemNodes()
    {
        $currentContentContext = $this->contentContextContainer->getContentContext();
        $contentContext = $this->contentContextFactory->create([
            'currentSite' => $currentContentContext->getCurrentSite(),
            'currentDomain' => $currentContentContext->getCurrentDomain(),
            'invisibleContentShown' => true,
            'inaccessibleContentShown' => true
        ]);
        $flowQuery = new FlowQuery([$contentContext->getNode($this->rootNodePath)]);

        $systemNodeIdentifiers = [];
        foreach ($flowQuery->find('[instanceof Nezaniel.SystemNodes:SystemNode]')->get() as $systemNode) {
            /** @var NodeInterface $systemNode */
            $nodeTypeName = $systemNode->getNodeType()->getName();
            $identifierPropertyName = $systemNode->getNodeType()->getConfiguration('systemNode.identifier');
            if ($identifierPropertyName) {
                $identifier = $systemNode->getProperty($identifierPropertyName);
                if (!$identifier) {
                    continue;
                }
            } else {
                $identifier = null;
            }
            $ancestorNodeTypeName = $systemNode->getNodeType()->getConfiguration('systemNode.ancestorToBeResolved');
            if ($ancestorNodeTypeName) {
                $systemNode = $this->fetchClosestAncestorNode($systemNode, $ancestorNodeTypeName);
            }

            $cacheEntryIdentifier = $this->resolveCacheEntryIdentifier($nodeTypeName, $identifier);
            $this->systemNodes = Arrays::setValueByPath($this->systemNodes, $cacheEntryIdentifier, $systemNode);
            $systemNodeIdentifiers = Arrays::setValueByPath($systemNodeIdentifiers, $cacheEntryIdentifier, $systemNode->getIdentifier());
        }
        $this->cache->set('systemNodeIdentifiers', $systemNodeIdentifiers);
    }


    /**
     * @param string $nodeTypeName
     * @param string $identifier
     * @return null|NodeInterface
     */
    public function getSystemNode($nodeTypeName, $identifier = null)
    {
        $cacheEntryIdentifier = $this->resolveCacheEntryIdentifier($nodeTypeName, $identifier);
        $systemNode = Arrays::getValueByPath($this->systemNodes, $cacheEntryIdentifier);
        if (is_null($systemNode)) {
            $systemNodeIdentifier = Arrays::getValueByPath($this->systemNodeIdentifiers, $cacheEntryIdentifier);
            if (!is_null($systemNodeIdentifier)) {
                $systemNode = $this->contentContextContainer->getContentContext()->getNodeByIdentifier($systemNodeIdentifier);
                $this->systemNodes = Arrays::setValueByPath($this->systemNodes, $cacheEntryIdentifier, $systemNode);
            }
        }

        return $systemNode;
    }

    /**
     * @param string $nodeTypeName
     * @return NodeInterface[]
     */
    public function getSystemNodes($nodeTypeName)
    {
        $cacheEntryIdentifier = $this->resolveCacheEntryIdentifier($nodeTypeName, null);
        $systemNodes = Arrays::getValueByPath($this->systemNodes, $cacheEntryIdentifier);
        if (is_null($systemNodes)) {
            $systemNodes = [];
            $systemNodeIdentifiers = Arrays::getValueByPath($this->systemNodeIdentifiers, $cacheEntryIdentifier);
            if (is_array($systemNodeIdentifiers)) {
                foreach ($systemNodeIdentifiers as $systemNodeIdentifier => $nodeIdentifier) {
                    $systemNodes[$systemNodeIdentifier] = $this->contentContextContainer->getContentContext()->getNodeByIdentifier($nodeIdentifier);
                }
            }
            $this->systemNodes = Arrays::setValueByPath($this->systemNodes, $cacheEntryIdentifier, $systemNodes);
        }

        return $systemNodes;
    }

    /**
     * @param NodeInterface $node
     * @return void
     */
    public function refreshCacheIfNecessary(NodeInterface $node)
    {
        if ($node->getNodeType()->isOfType('Nezaniel.SystemNodes:SystemNode')) {
            $this->initializeSystemNodes();
        }
    }


    /**
     * @param NodeInterface $systemNode
     * @param $ancestorNodeTypeName
     * @return NodeInterface
     */
    protected function fetchClosestAncestorNode(NodeInterface $systemNode, $ancestorNodeTypeName)
    {
        $flowQuery = new FlowQuery([$systemNode]);

        return $flowQuery->closest('[instanceof ' . $ancestorNodeTypeName . ']')->get(0);
    }

    /**
     * @param string $nodeTypeName
     * @param string $identifier
     * @return string
     */
    protected function resolveCacheEntryIdentifier($nodeTypeName, $identifier = null)
    {
        $cacheIdentifier = $this->formatCacheEntryIdentifier($nodeTypeName);
        if (!is_null($identifier)) {
            $cacheIdentifier .= '.' . $this->formatCacheEntryIdentifier($identifier);
        }

        return $cacheIdentifier;
    }

    /**
     * @param string $identifier
     * @return string
     */
    protected function formatCacheEntryIdentifier($identifier)
    {
        return str_replace(['.', ':', '\\', '|'], ['-', '-', '-', '_'], $identifier);
    }
}
