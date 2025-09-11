<?php
namespace Panniz\ProductFeatureColumn\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;

class FeatureChoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('feature_id', ChoiceType::class, [
                'label' => 'Feature to display',
                'choices' => $this->getFeatureChoices(), // Calls our helper method
                'required' => true,
                'help' => 'Choose the feature whose values will be displayed in the new column of the product list.',
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Save',
                'attr' => [
                    'class' => 'btn-primary',
                ],
            ]);
    }

    /**
     * Retrieves features using the static method of the Feature ObjectModel.
     * @return array
     */
    private function getFeatureChoices(): array
    {
        // Gets the current language ID from the PrestaShop context
        $idLang = \Context::getContext()->language->id;

        // Uses the static and reliable PrestaShop method
        $features = \Feature::getFeatures($idLang);

        $choices = [];
        if ($features) {
            foreach ($features as $feature) {
                // The structure is ['name' => 'Feature Name', 'id_feature' => 1]
                $choices[$feature['name']] = $feature['id_feature'];
            }
        }

        return $choices;
    }
}