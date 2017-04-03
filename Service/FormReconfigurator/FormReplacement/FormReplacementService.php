<?php
/**
 * @author Anton Zoffmann
 * @copyright dasistweb GmbH (http://www.dasistweb.de)
 * Date: 03.04.17
 * Time: 19:04
 */

namespace Barbieswimcrew\Bundle\DynamicFormBundle\Service\FormReconfigurator\FormReplacement;

use Barbieswimcrew\Bundle\DynamicFormBundle\Service\OptionsMerger\OptionsMergerService;
use Barbieswimcrew\Bundle\DynamicFormBundle\Form\Extension\RelatedFormTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\ResolvedFormTypeInterface;

class FormReplacementService
{
    /** @var FormBuilderInterface $builder */
    private $builder;
    /** @var OptionsMergerService */
    private $merger;

    public function __construct(FormBuilderInterface $builder, OptionsMergerService $merger)
    {
        $this->builder = $builder;
        $this->merger = $merger;
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
            return;
        }

        if (($resolvedType = $originForm->getConfig()->getType()) instanceof ResolvedFormTypeInterface) {
            $type = get_class($resolvedType->getInnerType());
        } else {
            $type = get_class($originForm->getConfig()->getType());
        }

        $mergedOptions = $this->merger->getMergedOptions($originForm, $overrideOptions, $hidden);

        # ATTENTION: this desicion-making property shall not be handled by any OptionsMerger which is under users controll.
        $mergedOptions[RelatedFormTypeExtension::OPTION_NAME_ALREADY_RECONFIGURED] = $blockFurtherReconfigurations;

        # setInheritData STOPS EVENT PROPAGATION DURING SAVEDATA()
        $replacementBuilder = $this->builder->create($originForm->getName(), $type, $mergedOptions);

        $replacementForm = $replacementBuilder->getForm();

        $parent = $originForm->getParent();

        if ($parent instanceof FormInterface) {
            $parent->offsetSet($replacementForm->getName(), $replacementForm);
        }


    }
}