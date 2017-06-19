<?php
/**
 * Created by PhpStorm.
 * User: marco
 * Date: 10.06.17
 * Time: 19:19
 */

namespace Typo3Api\Tca\Field;


use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Typo3Api\Tca\TcaConfiguration;

abstract class TcaField implements TcaConfiguration
{
    /**
     * @var array
     */
    private $options;

    /**
     * A cache for option resolvers to speed up duplicate usage.
     * @var array
     */
    private static $optionResolvers = [];

    /**
     * CommonField constructor.
     * @param string $name
     * @param array $options
     */
    public final function __construct(string $name, array $options = [])
    {
        // nicer creation syntax when passing name as a direct parameter instead of expecting an option
        $options['name'] = $name;

        $optionResolver = $this->getOptionResolver();
        $this->options = $optionResolver->resolve($options);
    }

    private function getOptionResolver()
    {
        if (isset(self::$optionResolvers[get_class($this)])) {
            return self::$optionResolvers[get_class($this)];
        }

        $optionResolver = new OptionsResolver();
        $this->configureOptions($optionResolver);
        self::$optionResolvers[get_class($this)] = $optionResolver;
        return $optionResolver;
    }

    protected function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired([
            'name',
            'dbType'
        ]);
        $resolver->setDefaults([
            'label' => function (Options $options) {
                $splitName = preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $options['name']);
                return ucfirst(trim(strtolower($splitName)));
            },
            'exclude' => false,
            'localize' => true,
            'displayCond' => null,
            'useAsLabel' => false,
            'searchField' => false,
            'useForRecordType' => false,
        ]);

        $resolver->setAllowedTypes('name', 'string');
        $resolver->setAllowedTypes('label', 'string');
        $resolver->setAllowedTypes('exclude', 'bool');
        $resolver->setAllowedTypes('dbType', 'string');
        $resolver->setAllowedTypes('localize', 'bool');
        $resolver->setAllowedTypes('displayCond', ['string', 'null']);
        $resolver->setAllowedTypes('useAsLabel', 'bool');
        $resolver->setAllowedTypes('searchField', 'bool');
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function getOption(string $name)
    {
        return $this->options[$name];
    }

    public function modifyCtrl(array &$ctrl, string $tableName)
    {
        if ($this->getOption('useAsLabel')) {
            if (!isset($ctrl['label'])) {
                $ctrl['label'] = $this->getOption('name');
            } else {
                if (!isset($ctrl['label_alt'])) {
                    $ctrl['label_alt'] = $this->getOption('name');
                } else {
                    $ctrl['label_alt'] .= ', ' . $this->getOption('name');
                }
            }
        }

        if ($this->getOption('searchField')) {
            if (!isset($ctrl['searchFields'])) {
                $ctrl['searchFields'] = $this->getOption('name');
            } else if (strpos($ctrl['searchFields'], $this->getOption('name')) === false) {
                $ctrl['searchFields'] .= ', ' . $this->getOption('name');
            }
        }

        if ($this->getOption('useForRecordType')) {
            if (isset($ctrl['type'])) {
                $msg = "Only one field can specify the record type for table $tableName.";
                $msg .= " Tried using field " . $this->getOption('name') . " as type field.";
                $msg .= " Field " . $ctrl['type'] . " is already defined as type field.";
                throw new \RuntimeException($msg);
            }

            $ctrl['type'] = $this->getOption('name');
        }
    }

    public function getColumns(string $tableName): array
    {
        return [
            $this->getOption('name') => [
                'label' => $this->getOption('label'),
                'exclude' => $this->getOption('exclude'),
                'config' => $this->getFieldTcaConfig($tableName),
                'l10n_mode' => $this->getOption('localize') ? '' : 'exclude',
                'l10n_display' => $this->getOption('localize') ? '' : 'defaultAsReadonly',
                'displayCond' => $this->getOption('displayCond'),
            ]
        ];
    }

    public function getPalettes(string $tableName): array
    {
        return [];
    }

    abstract public function getFieldTcaConfig(string $tableName);

    public function getDbTableDefinitions(string $tableName): array
    {
        $name = addslashes($this->getOption('name'));
        return [
            $tableName => [
                "`$name` " . $this->getOption('dbType')
            ]
        ];
    }

    public function getShowItemString(string $tableName): string
    {
        return $this->getOption('name');
    }
}