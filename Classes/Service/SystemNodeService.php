<?php
namespace Nezaniel\SystemNodes\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
*                                                                        */
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Utility\Arrays;
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
        $contentContext = $this->contentContextContainer->getContentContext();
        $flowQuery = new FlowQuery([$contentContext->getNode($this->rootNodePath)]);

        $systemNodeIdentifiers = [];
        foreach ($flowQuery->find('[instanceof Nezaniel.SystemNodes:SystemNode]')->get() as $systemNode) {
            /** @var NodeInterface $systemNode */
            $nodeTypeName = $systemNode->getNodeType()->getName();
            $identifier = $systemNode->getNodeType()->getConfiguration('systemNode.identifier');
            if ($identifier) {
                $identifier = $systemNode->getProperty($identifier);
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
     * @return NodeInterface|null|NodeInterface[]
     */
    public function getSystemNodes($nodeTypeName, $identifier = null)
    {
        $cacheEntryIdentifier = $this->resolveCacheEntryIdentifier($nodeTypeName, $identifier);
        $systemNodes = Arrays::getValueByPath($this->systemNodes, $cacheEntryIdentifier);
        if (is_null($systemNodes)) {
            $systemNodeIdentifier = Arrays::getValueByPath($this->systemNodeIdentifiers, $cacheEntryIdentifier);
            if (!is_null($systemNodeIdentifier)) {
                if (is_array($systemNodeIdentifier)) {
                    $systemNodes = [];
                    foreach ($systemNodeIdentifier as $identifier) {
                        $systemNodes[] = $this->contentContextContainer->getContentContext()->getNodeByIdentifier($identifier);
                    }
                } else {
                    $systemNodes = $this->contentContextContainer->getContentContext()->getNodeByIdentifier($systemNodeIdentifier);
                }
                $this->systemNodes = Arrays::setValueByPath($this->systemNodes, $cacheEntryIdentifier, $systemNodes);
            }
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
    protected function resolveCacheEntryIdentifier($nodeTypeName, $identifier = null) {
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
