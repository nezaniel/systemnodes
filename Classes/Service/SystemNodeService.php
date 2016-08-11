<?php
namespace Nezaniel\SystemNodes\Service;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
*                                                                        */
use TYPO3\Eel\FlowQuery\FlowQuery;
use TYPO3\Flow\Annotations as Flow;
use TYPO3\Flow\Cache\Frontend\VariableFrontend;
use TYPO3\Flow\Utility\Arrays;
use TYPO3\Neos\Domain\Service\ContentContext;
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
            $this->systemNodeIdentifiers = $this->cache->get('systemNodeIdentifiers');
        }
    }


    /**
     * @return void
     */
    protected function initializeSystemNodes()
    {
        $contentContext = $this->getContentContext(true);
        $flowQuery = new FlowQuery([$contentContext->getNode($this->rootNodePath)]);

        $this->systemNodeIdentifiers = [];
        foreach ($flowQuery->find('[instanceof Nezaniel.SystemNodes:SystemNode]')->get() as $systemNode) {
            $this->initializeSystemNode($systemNode);
        }

        $this->cache->set('systemNodeIdentifiers', $this->systemNodeIdentifiers);
    }

    /**
     * @param NodeInterface $systemNode
     */
    protected function initializeSystemNode(NodeInterface $systemNode)
    {
        $identifierPaths = $systemNode->getNodeType()->getConfiguration('systemNode.paths');
        if (!empty($identifierPaths)) {
            foreach ($identifierPaths as $identifierPathName => $identifierPath) {
                $identifierValues = $this->resolveIdentifierValues($systemNode, $identifierPathName, $identifierPath);
                if (!is_array($identifierValues)) {
                    continue;
                }

                $this->cacheSystemNode($systemNode, $identifierValues);
            }
        } else {
            $this->cacheSystemNode($systemNode, [$systemNode->getNodeType()->getName()]);
        }
    }

    /**
     * @param NodeInterface $systemNode
     * @param $identifierPathName
     * @param $identifierPath
     * @return array|null
     */
    protected function resolveIdentifierValues(NodeInterface $systemNode, $identifierPathName, $identifierPath)
    {
        $identifierValues = [
            $this->formatCacheEntryIdentifier($systemNode->getNodeType()->getConfiguration('systemNode.nodeTypeIdentifier') ?: $systemNode->getNodeType()->getName()),
            $this->formatCacheEntryIdentifier($identifierPathName)
        ];
        foreach ($identifierPath as $propertyName => $active) {
            $identifierValue = $active ? $this->formatCacheEntryIdentifier($systemNode->getProperty($propertyName)) : null;
            if (!$identifierValue) {
                // Don't save node's identifier if it cannot be completely resolved
                return null;
            }
            $identifierValues[] = $identifierValue;
        }

        return $identifierValues;
    }

    /**
     * @param NodeInterface $systemNode
     * @param array $identifierValues
     * @return void
     */
    protected function cacheSystemNode(NodeInterface $systemNode, array $identifierValues)
    {
        $ancestorNodeTypeName = $systemNode->getNodeType()->getConfiguration('systemNode.ancestorToBeResolved');
        if ($ancestorNodeTypeName) {
            $systemNode = $this->fetchClosestAncestorNode($systemNode, $ancestorNodeTypeName);
        }
        if ($systemNode) {
            $this->systemNodeIdentifiers = Arrays::setValueByPath($this->systemNodeIdentifiers, $identifierValues, $systemNode->getIdentifier());
        }
    }


    /**
     * @param array $identifier
     * @param boolean $force
     * @return null|NodeInterface
     */
    public function getSystemNode(array $identifier, $force = false)
    {
        $systemNodeIdentifier = $this->getSystemNodeIdentifier($identifier);
        if (!is_null($systemNodeIdentifier)) {
            $context = $this->getContentContext($force);

            return $context->getNodeByIdentifier($systemNodeIdentifier);
        } else {
            return null;
        }
    }

    /**
     * @param array $identifier
     * @return string|null
     */
    public function getSystemNodeIdentifier(array $identifier)
    {
        array_walk($identifier, function (&$cacheEntryIdentifier) {
            $cacheEntryIdentifier = $this->formatCacheEntryIdentifier($cacheEntryIdentifier);
        });

        return Arrays::getValueByPath($this->systemNodeIdentifiers, $identifier);
    }

    /**
     * @param array $identifier
     * @param boolean $force
     * @return NodeInterface[]
     */
    public function getSystemNodes(array $identifier, $force = false)
    {
        array_walk($identifier, function (&$cacheEntryIdentifier) {
            $cacheEntryIdentifier = $this->formatCacheEntryIdentifier($cacheEntryIdentifier);
        });

        $context = $this->getContentContext($force);
        $systemNodes = [];
        $systemNodeIdentifiers = Arrays::getValueByPath($this->systemNodeIdentifiers, $identifier);
        if (is_array($systemNodeIdentifiers)) {
            foreach ($systemNodeIdentifiers as $systemNodeIdentifier => $nodeIdentifier) {
                $systemNodes[$systemNodeIdentifier] = $context->getNodeByIdentifier($nodeIdentifier);
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
            $currentPath = [];

            $recursiveIterator = function (array $items) use (&$recursiveIterator, &$currentPath, $node) {
                foreach ($items as $key => $entry) {
                    $currentPath[] = $key;
                    if (is_array($entry)) {
                        $recursiveIterator($entry);
                    } elseif ($entry === $node->getIdentifier()) {
                        $this->systemNodeIdentifiers = Arrays::unsetValueByPath($this->systemNodeIdentifiers, $currentPath);
                    }
                    array_pop($currentPath);
                }
            };

            $recursiveIterator($this->systemNodeIdentifiers);

            $this->initializeSystemNode($node);
            $this->cache->set('systemNodeIdentifiers', $this->systemNodeIdentifiers);
        }
    }


    /**
     * @param boolean $force
     * @return ContentContext
     */
    protected function getContentContext($force = false)
    {
        if ($force) {
            $currentContext = $this->contentContextContainer->getContentContext();

            return $this->contentContextFactory->create([
                'invisibleContentShown' => true,
                'inaccessibleContentShown' => true,
                'currentDomain' => $currentContext->getCurrentDomain(),
                'currentSite' => $currentContext->getCurrentSite(),
            ]);
        } else {
            return $this->contentContextContainer->getContentContext();
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
     * @param string $identifier
     * @return string
     */
    protected function formatCacheEntryIdentifier($identifier)
    {
        return str_replace(['.', ':', '\\', '|'], ['-', '-', '-', '_'], $identifier);
    }
}
