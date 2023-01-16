<?php declare(strict_types=1);
namespace CmapImport\Form;

use Laminas\Form\Element;
use Laminas\Form\Form;
use Omeka\Form\Element\ItemSetSelect;

class ImportForm extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'itemSet',
            'type' => ItemSetSelect::class,
            'options' => [
                'label' => 'Import into', // @translate
                'info' => 'Required. Import items into this item set.', // @translate
                'empty_option' => 'Select item set…', // @translate
                'query' => ['is_open' => true],
            ],
            'attributes' => [
                'required' => true,
                'class' => 'chosen-select',
                'id' => 'library-item-set',
            ],
        ]);

        $this->add([
            'name' => 'file',
            'type' => Element\File::class,
            'options' => [
                'label' => 'IVML file', // @translate
            ],
            'attributes' => [
                'id' => 'file',
                'required' => true,
            ],
        ]);

        $inputFilter = $this->getInputFilter();

        $inputFilter->add([
            'name' => 'itemSet',
            'required' => true,
            'filters' => [
                ['name' => 'Int'],
            ],
            'validators' => [
                ['name' => 'Digits'],
            ],
        ]);
    }
}
