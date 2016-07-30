<?php

namespace Bolt\Configuration\Validation;

use Bolt\Config;
use Bolt\Configuration\LowlevelChecks;
use Bolt\Configuration\ResourceManager;
use Bolt\Controller;
use Bolt\Exception\BootException;
use Symfony\Component\HttpFoundation\Response;

/**
 * System validator.
 *
 * @internal For BC. Use of this class should not include use of LowlevelChecks
 *           methods/properties.
 *
 * @author Gawain Lynch <gawain.lynch@gmail.com>
 */
class Validator extends LowlevelChecks implements ValidatorInterface
{
    /** @var Controller\Exception */
    private $exceptionController;
    /** @var Config */
    private $configManager;
    /** @var ResourceManager */
    private $resourceManager;
    /** @var array */
    private $check = [
        'configuration' => Configuration::class,
        'database'      => Database::class,
        'magicQuotes'   => MagicQuotes::class,
        'safeMode'      => SafeMode::class,
        'cache'         => Cache::class,
        'apache'        => Apache::class,
        'config'        => ConfigurationFile::class,
        'contenttypes'  => ConfigurationFile::class,
        'menu'          => ConfigurationFile::class,
        'permissions'   => ConfigurationFile::class,
        'routing'       => ConfigurationFile::class,
        'taxonomy'      => ConfigurationFile::class,
    ];

    /**
     * Constructor.
     *
     * @param Controller\Exception $exceptionController
     * @param ResourceManager      $resourceManager
     */
    public function __construct(Controller\Exception $exceptionController, Config $config, ResourceManager $resourceManager)
    {
        parent::__construct($resourceManager);
        $this->exceptionController = $exceptionController;
        $this->configManager = $config;
        $this->resourceManager = $resourceManager;
    }

    /**
     * {@inheritdoc}
     */
    public function add($checkName, $className, $prepend = false)
    {
        if ($prepend) {
            $this->check = [$checkName => $className] + $this->check;
        } else {
            $this->check[$checkName] = $className;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function remove($checkName)
    {
        unset($this->check[$checkName]);
    }

    /**
     * {@inheritdoc}
     */
    public function check($checkName)
    {
        $className = $this->check[$checkName];

        return $this
            ->getValidator($className, $checkName)
            ->check($this->exceptionController)
        ;
    }

    /**
     * Run system installation checks.
     */
    public function checks()
    {
        if ($this->disableApacheChecks) {
            unset($this->check['apache']);
        }

        foreach ($this->check as $checkName => $className) {
            $response = $this
                ->getValidator($className, $checkName)
                ->check($this->exceptionController)
            ;
            if ($response instanceof Response) {
                return $response;
            }
        }

        return null;
    }

    /**
     * Get a validator object from a given class name.
     *
     * @param string $className
     * @param mixed  $constructorArgs
     *
     * @return ValidationInterface
     */
    private function getValidator($className, $constructorArgs)
    {
        /** @var ValidationInterface $validator */
        $validator = new $className($constructorArgs);
        if (!$validator instanceof ValidationInterface) {
            throw new BootException(sprintf('System validator was given a validation class %s that does not implement %s', $className, ValidationInterface::class));
        }
        if ($validator instanceof ResourceManagerAwareInterface) {
            $validator->setResourceManager($this->resourceManager);
        }
        if ($validator instanceof ConfigAwareInterface) {
            $validator->setConfig($this->configManager);
        }

        return $validator;
    }
}
