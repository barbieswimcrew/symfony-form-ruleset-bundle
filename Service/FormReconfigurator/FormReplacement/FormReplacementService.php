<?php

namespace Barbieswimcrew\Bundle\DynamicFormBundle\Service\FormReconfigurator\FormReplacement;

use Barbieswimcrew\Bundle\DynamicFormBundle\Form\Extension\RelatedFormTypeExtension;
use Barbieswimcrew\Bundle\DynamicFormBundle\Service\FormPropertyHelper\FormPropertyHelper;
use Barbieswimcrew\Bundle\DynamicFormBundle\Service\OptionsMerger\OptionsMergerService;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;

/**
 * Class FormReplacementService
 * @package Barbieswimcrew\Bundle\DynamicFormBundle\Service\FormReconfigurator\FormReplacement
 */
class FormReplacementService
{
    /** @var FormBuilderInterface $builder */
    private $builder;

    /** @var OptionsMergerService */
    private $merger;

    /** @var FormPropertyHelper  */
    private $propertyHelper;

    /**
     * FormReplacementService constructor.
     * @param FormBuilderInterface $builder
     * @param OptionsMergerService $merger
     * @param FormPropertyHelper $formPropertyHelper
     */
    public function __construct(FormBuilderInterface $builder, OptionsMergerService $merger, FormPropertyHelper $formPropertyHelper)
    {
        $this->builder = $builder;
        $this->merger = $merger;
        $this->propertyHelper = $formPropertyHelper;
    }

    /**
     * sets a new configured form to the parent of the original form
     * @param FormInterface $originForm
     * @param array $overrideOptions
     * @param boolean $hidden
     * @param boolean $blockFurtherReconfigurations
     * @author Anton Zoffmann
     * @return void
     */
    public function replaceForm(FormInterface $originForm, array $overrideOptions, $hidden, $blockFurtherReconfigurations)
    {
        # the information we need is not whether the form was already reconfigured but more if further reconfiguration is allowed
        # e.g. we have a 2-hierarchy toggle and the "father" toggle is turned on - children should be allowed to do their own reconfiguration
        # e.g. BUT if the parent toggle is off - the children SHALL NOT reconfigure any of the fields, already reconfigured from the parent toggle
        if ($originForm->getConfig()->getOption(RelatedFormTypeExtension::OPTION_NAME_ALREADY_RECONFIGURED) === true) {
            return;     // @codeCoverageIgnore
        }

        /** @var FormTypeInterface $type */
        $type = $this->propertyHelper->getConfiguredFormTypeByForm($originForm);

        /** @var array $originOptions */
        $originOptions = $originForm->getConfig()->getOptions();

        /** @var array $mergedOptions */
        $mergedOptions = $this->merger
            ->getOptionsMerger($originForm)
            ->mergeOptions($originOptions, $overrideOptions, $hidden);

        # ATTENTION: this decision-making property shall not be handled by any OptionsMerger which is under users control.
        $mergedOptions[RelatedFormTypeExtension::OPTION_NAME_ALREADY_RECONFIGURED] = $blockFurtherReconfigurations;

        # setInheritData STOPS EVENT PROPAGATION DURING SAVEDATA()
        $replacementBuilder = $this->builder->create($originForm->getName(), get_class($type), $mergedOptions);

        $replacementForm = $replacementBuilder->getForm();

        $parent = $originForm->getParent();

        if ($parent instanceof FormInterface) {
            $parent->offsetSet($replacementForm->getName(), $replacementForm);
        }


    }
}