<?php

namespace Panniz\ProductFeatureColumn\Controller\Admin;

use Panniz\ProductFeatureColumn\Form\Type\FeatureChoiceType;
use PrestaShop\PrestaShop\Adapter\Configuration;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatorInterface;

class ConfigureController extends FrameworkBundleAdminController
{
    public function index(Request $request, Configuration $configuration, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(FeatureChoiceType::class, [
            'feature_id' => $configuration->get('PANNIZ_PFC_FEATURE_ID'),
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $configuration->set('PANNIZ_PFC_FEATURE_ID', $data['feature_id']);

            $this->addFlash('success', $translator->trans('Configuration saved successfully!', [], 'Admin.Notifications.Success'));

            return $this->redirectToRoute('panniz_configure_feature_column');
        }

        return $this->render('@Modules/pannizproductfeaturecolumn/views/templates/admin/configure.html.twig', [
            'configureForm' => $form->createView(),
        ]);
    }
}
