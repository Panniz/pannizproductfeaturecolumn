<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/vendor/autoload.php';

use PrestaShop\PrestaShop\Core\Grid\Column\Type\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\GridDefinitionInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class PannizProductFeatureColumn extends Module
{
    public const CONFIG_KEY_FEATURE_ID = 'PANNIZ_PFC_FEATURE_ID';

    public function __construct()
    {
        $this->name = 'pannizproductfeaturecolumn';
        $this->tab = 'administration';
        $this->version = '1.0.0';
        $this->author = 'Panniz';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('Panniz - Product Feature Column', [], 'Modules.Pannizproductfeaturecolumn.Admin');
        $this->description = $this->trans('Adds a custom column with the value of a feature to the backoffice product list.', [], 'Modules.Pannizproductfeaturecolumn.Admin');
        $this->ps_versions_compliancy = ['min' => '8.1.0', 'max' => _PS_VERSION_];
    }

    public function install(): bool
    {
        return parent::install()
            && $this->registerHook('actionProductGridDefinitionModifier')
            && $this->registerHook('actionProductGridQueryBuilderModifier');
    }

    public function uninstall(): bool
    {
        Configuration::deleteByName(self::CONFIG_KEY_FEATURE_ID);
        return parent::uninstall();
    }

    /**
     * Redirects to the Symfony configuration page.
     * This is the correct and robust method.
     */
    public function getContent(): void
    {
        // 1. Ottieni il servizio router di Symfony dal container.
        $router = $this->get('router');

        // 2. Genera l'URL *assoluto* per la nostra rotta Symfony.
        $url = $router->generate(
            'panniz_configure_feature_column', // Il nome della nostra rotta
            [], // Eventuali parametri per la rotta (qui non ne servono)
            \Symfony\Component\Routing\RouterInterface::ABSOLUTE_URL
        );

        // 3. Usa Tools::redirect() per reindirizzare a un URL completo.
        Tools::redirectAdmin($url);
    }

    /**
     * Hook per modificare la *definizione* della griglia prodotti.
     * Aggiunge la nuova colonna alla struttura della griglia.
     */
    public function hookActionProductGridDefinitionModifier(array $params): void
    {
        /** @var GridDefinitionInterface $definition */
        $definition = $params['definition'];
        $featureId = (int) Configuration::get(self::CONFIG_KEY_FEATURE_ID);

        if (!$featureId) {
            return; // Non fare nulla se nessuna caratteristica è configurata
        }

        // Ottieni il nome della caratteristica per l'header della colonna
        $feature = new Feature($featureId, $this->context->language->id);
        $columnName = $feature->name ?: $this->trans('Valore Caratteristica', [], 'Modules.Pannizproductfeaturecolumn.Admin');

        $newColumn = (new DataColumn('feature_value'))
            ->setName($columnName)
            ->setOptions([
                'field' => 'feature_value',
                'sortable' => false, // L'ordinamento su un campo joinato può essere complesso/lento
            ]);

        // Aggiunge la nuova colonna dopo la colonna "active" (Status)
        $definition->getColumns()->addAfter('active', $newColumn);

        $definition->getFilters()->add(
            (new Filter('feature_value', ChoiceType::class)) // <-- Il tipo di campo per il filtro
                ->setAssociatedColumn('feature_value')
                ->setTypeOptions([
                    'required' => false,
                    'choices' => $this->getFeatureValuesForFilter(), // <-- MODIFICA: Carica le opzioni dinamicamente
                    'placeholder' => $this->trans('Tutti i valori', [], 'Modules.Pannizproductfeaturecolumn.Admin'), // <-- MODIFICA: Placeholder per il select
                ])
        );
    }

    /**
     * Hook per modificare la *query* della griglia prodotti.
     */
    public function hookActionProductGridQueryBuilderModifier(array $params): void
    {
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $params['search_query_builder'];
        /** @var array $filters */
        $filters = $params['search_criteria']->getFilters();

        $idLang = $this->context->language->id;
        $featureId = (int) Configuration::get(self::CONFIG_KEY_FEATURE_ID);

        if (!$featureId) {
            return;
        }

        // Selezioni e join rimangono invariati
        $queryBuilder->addSelect('fvl.value AS feature_value');
        $queryBuilder->leftJoin('p', '`' . _DB_PREFIX_ . 'feature_product`', 'fp', 'p.id_product = fp.id_product AND fp.id_feature = :featureId');
        $queryBuilder->leftJoin('fp', '`' . _DB_PREFIX_ . 'feature_value_lang`', 'fvl', 'fp.id_feature_value = fvl.id_feature_value AND fvl.id_lang = :idLang');
        $queryBuilder->setParameter('featureId', $featureId);
        $queryBuilder->setParameter('idLang', $idLang);

        if (isset($filters['feature_value']) && !empty($filters['feature_value'])) {
            $queryBuilder->andWhere('fvl.value = :feature_value_filter'); // <-- MODIFICA: Usa "=" per una corrispondenza esatta
            $queryBuilder->setParameter('feature_value_filter', $filters['feature_value']); // <-- MODIFICA: Rimuovi i '%'
        }

        // La logica di ordinamento non cambia
        $orderBy = $params['search_criteria']->getOrderBy();
        $orderWay = $params['search_criteria']->getOrderWay();

        if ($orderBy === 'feature_value') {
            $queryBuilder->orderBy('feature_value', $orderWay);
        }
    }

    /**
     * 3. NUOVO METODO: Recupera tutti i valori unici per la caratteristica configurata.
     * Questi valori popoleranno il menu a discesa del filtro.
     */
    private function getFeatureValuesForFilter(): array
    {
        $featureId = (int) Configuration::get(self::CONFIG_KEY_FEATURE_ID);
        $idLang = $this->context->language->id;

        if (!$featureId) {
            return [];
        }

        $query = new DbQuery();
        $query->select('DISTINCT fvl.value');
        $query->from('feature_product', 'fp');
        $query->innerJoin('feature_value', 'fv', 'fv.id_feature_value = fp.id_feature_value');
        $query->innerJoin('feature_value_lang', 'fvl', 'fvl.id_feature_value = fv.id_feature_value');
        $query->where('fp.id_feature = ' . $featureId);
        $query->where('fvl.id_lang = ' . $idLang);
        $query->orderBy('fvl.value ASC');

        $results = Db::getInstance()->executeS($query);

        if (!$results) {
            return [];
        }

        // Trasforma l'array di risultati (es. [['value' => 'Rosso'], ['value' => 'Verde']])
        // in un formato adatto per ChoiceType (es. ['Rosso' => 'Rosso', 'Verde' => 'Verde'])
        $values = array_column($results, 'value');

        return array_combine($values, $values);
    }
}
