<?php
namespace Nezaniel\SystemNodes;

/*                                                                       *
 * This script belongs to the TYPO3 Flow package "Nezaniel.SystemNodes". *
 *                                                                       */

use TYPO3\Flow\Core\Bootstrap;
use TYPO3\Flow\Package\Package as BasePackage;

/**
 * The Nezaniel.SystemNodes Package
 */
class Package extends BasePackage
{

    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(
            'TYPO3\TYPO3CR\Domain\Model\Node', 'nodeUpdated',
            'Nezaniel\SystemNodes\Service\SystemNodeService', 'refreshCacheIfNecessary'
        );
        $dispatcher->connect(
            'TYPO3\TYPO3CR\Domain\Model\Node', 'nodeAdded',
            'Nezaniel\SystemNodes\Service\SystemNodeService', 'refreshCacheIfNecessary'
        );
        $dispatcher->connect(
            'TYPO3\TYPO3CR\Domain\Model\Node', 'nodeRemoved',
            'Nezaniel\SystemNodes\Service\SystemNodeService', 'refreshCacheIfNecessary'
        );
    }
}
