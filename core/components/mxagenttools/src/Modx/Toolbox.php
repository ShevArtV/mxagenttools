<?php

namespace MxAgentTools\Modx;

use MxAgentTools\Core\BatchRollback;
use MxAgentTools\Core\Entities;
use MxAgentTools\Core\Executor;
use MxAgentTools\Core\IdMap;
use MxAgentTools\Core\Processor;
use MxAgentTools\Core\QueryGuard;
use MxAgentTools\Core\Uploads;
use MxAgentTools\Entity;
use MxAgentTools\Tool;
use MxAgentTools\Image\Images;
use MxAgentTools\Tool\Batch\BatchIds;
use MxAgentTools\Tool\ToolInterface;

/**
 * Сборка инструментов и сущностей сайта. Какие пакеты-расширения есть — по каталогам моделей (дёшево, при
 * регистрации эндпоинтов); сами модели грузятся prepare() перед вызовом: без модели, например, collections
 * resource/update молча взял бы базовый процессор вместо CollectionContainerUpdateProcessor.
 * Инструмент без своего пакета не создаётся — его эндпоинта нет в каталоге.
 */
class Toolbox
{
    const MINISHOP2 = 'minishop2';
    const MSOPTIONSPRICE = 'msoptionsprice';
    const COLLECTIONS = 'collections';
    const CLIENTCONFIG = 'clientconfig';

    /** @var \modX */
    private $modx;
    /** @var array|null */
    private $packages;
    /** @var bool */
    private $prepared = false;
    /** @var array|null */
    private $services;
    /** @var SiteExtensions */
    private $site;

    public function __construct(\modX $modx)
    {
        $this->modx = $modx;
        $this->site = new SiteExtensions();
    }

    /** Установленные пакеты-расширения; msOptionsPrice2 — только вместе с ms2 (фото модификаций — из галереи ms2) */
    public function packages(): array
    {
        if ($this->packages === null) {
            $this->packages = [];
            foreach ([self::MINISHOP2 => 'minishop2/', self::MSOPTIONSPRICE => 'msoptionsprice/',
                self::COLLECTIONS => 'collections/', self::CLIENTCONFIG => 'clientconfig/'] as $package => $dir) {
                if (is_dir(MODX_CORE_PATH . "components/{$package}/model/{$dir}")) {
                    $this->packages[] = $package;
                }
            }
            if (!$this->has(self::MINISHOP2)) {
                $this->packages = array_values(array_diff($this->packages, [self::MSOPTIONSPRICE]));
            }
        }
        return $this->packages;
    }

    public function has(string $package): bool
    {
        return in_array($package, $this->packages(), true);
    }

    /** Модели пакета и расширений — перед вызовом инструмента */
    public function prepare(): void
    {
        if ($this->prepared) {
            return;
        }
        $this->modx->addPackage('mxagenttools', $this->corePath() . 'model/');
        if ($this->has(self::MINISHOP2)) {
            $this->modx->getService('miniShop2', 'miniShop2', MODX_CORE_PATH . 'components/minishop2/model/minishop2/');
            $this->modx->loadClass('msProduct');
        }
        foreach ([self::MSOPTIONSPRICE => 'msopModification', self::COLLECTIONS => 'CollectionContainer',
            self::CLIENTCONFIG => 'cgSetting'] as $package => $class) {
            if ($this->has($package)) {
                $this->modx->addPackage($package, MODX_CORE_PATH . "components/{$package}/model/");
                $this->modx->loadClass($class);
            }
        }
        // Закрытые классы сайта — до первого query: модель, загруженная плагином сайта, иначе читалась бы целиком
        QueryGuard::deny($this->site()['denied']);
        $this->prepared = true;
    }

    /** Инструменты, сущности и закрытые классы от плагинов сайта (mxAgentToolsOnRegister) */
    private function site(): array
    {
        return $this->site->load($this->modx, $this->services());
    }

    public function executor(): Executor
    {
        $this->prepare();
        return new Executor($this->entities(), $this->journal(), (string)$this->modx->user->get('username'));
    }

    /** Где порция в выборке: total, pages (по batch_limit), page — для ответа пакетного инструмента */
    public function selectionInfo(string $selection, int $page): array
    {
        $s = $this->services();
        $total = $s['selections']->total($selection);
        return ['id' => $selection, 'total' => $total, 'pages' => (int)ceil($total / $s['batch']->limit()), 'page' => $page,
            'page_size' => $s['batch']->limit()];
    }

    public function batchRollback(): BatchRollback
    {
        return new BatchRollback($this->executor(), $this->journal());
    }

    public function journal(): DbJournal
    {
        $this->prepare();
        return new DbJournal($this->modx, (int)$this->modx->getOption('mxagenttools.journal_depth', null, 5000));
    }

    /** Настройки читаются при вызове: объект создаётся и при регистрации эндпоинтов, поэтому без prepare() */
    public function uploads(): Uploads
    {
        return new Uploads(new DbUploadStorage($this->modx, new UploadBlobs($this->tmpPath() . '/uploads')), (int)$this->modx->getOption('mxagenttools.upload_ttl', null, 3600),
            (int)round((float)$this->modx->getOption('mxagenttools.upload_max_mb', null, 20) * 1048576), $this->tmpPath(), new Images());
    }

    /** Умолчания сжатия картинок при приёме — настройки mxagenttools.image_* */
    public function imageDefaults(): array
    {
        $o = function (string $k, $d) {
            return $this->modx->getOption('mxagenttools.' . $k, null, $d);
        };
        return ['max_side' => (int)$o('image_max_side', 2000), 'quality' => (int)$o('image_quality', 82),
            'format' => (string)$o('image_format', 'keep'), 'strip' => (bool)$o('image_strip_metadata', 1)];
    }

    /** @return ToolInterface[] */
    public function tools(): array
    {
        $m = $this->modx;
        $s = $this->services();
        $tools = [
            new Tool\Resource\ResourceGet($m, $s['resource']), new Tool\Resource\ResourceFind($m),
            new Tool\Resource\ResourceChildren($m), new Tool\Resource\ResourceCreate($m, $s['processor'], $s['tvs'], $s['resource']),
            new Tool\Resource\ResourceUpdate($s['resource']), new Tool\Resource\ResourceDelete($s['processor']),
            new Tool\Resource\ResourceUndelete($s['processor']),
            new Tool\System\TemplateList($m, $s['tvs']), new Tool\System\ContextList($m), new Tool\System\Query($m),
            new Tool\Tv\TvGet($s['tvs'], $s['tv']), new Tool\Tv\TvList($m), new Tool\Tv\TvCreate($s['processor'], $s['tvs'], $s['tv']),
            new Tool\Tv\TvUpdate($s['processor'], $s['tv']), new Tool\Tv\TvDelete($s['processor']),
            new Tool\Setting\SettingGet($m), new Tool\Setting\SettingUpdate($s['setting']),
            new Tool\Code\ElementList($m), new Tool\Code\ElementGet($m, $s['code']), new Tool\Code\FileList($s['code']),
            new Tool\Code\FileRead($s['code']),
            new Tool\Batch\ResourceSelect($m, $s['selections'], $s['batch']->limit()),
            new Tool\Batch\ResourceBatch($s['batch'], $s['resource']),
        ];
        if ($this->has(self::MINISHOP2)) {
            $mods = $s['mods'];
            array_push($tools, new Tool\Gallery\GalleryGet($s['files'], $mods), new Tool\Gallery\GalleryUpload($s['files'], $this->uploads()),
                new Tool\Gallery\GalleryRemove($s['files'], $mods), new Tool\Gallery\GallerySort($s['files']),
                new Tool\Batch\ProductSelect($m, $s['selections'], $s['batch']->limit()),
                new Tool\Batch\ProductCategories($s['batch'], $s['resource']),
                new Tool\Batch\ProductPrices($s['batch'], $s['resource'], $mods),
                new Tool\Link\ProductLinkGet($s['links']), new Tool\Link\ProductLinkCreate($s['links']),
                new Tool\Link\ProductLinkDelete($s['links']), new Tool\Link\ProductLinkSet($s['links'], $s['batch']));
        }
        if ($this->has(self::MSOPTIONSPRICE)) {
            foreach ([Tool\Modification\ModificationList::class, Tool\Modification\ModificationCreate::class,
                Tool\Modification\ModificationUpdate::class, Tool\Modification\ModificationDelete::class,
                Tool\Modification\ModificationImages::class] as $class) {
                $tools[] = new $class($s['mods'], $s['files']);
            }
            $tools[] = new Tool\Modification\ModificationExport($s['mods'], new ExternalIds($m));
        }
        if ($this->has(self::CLIENTCONFIG)) {
            array_push($tools, new Tool\ClientConfig\ClientConfigGet($m, $s['clientconfig']),
                new Tool\ClientConfig\ClientConfigUpdate($s['clientconfig']));
        }
        return array_merge($tools, $this->site()['tools']);
    }

    public function entities(): Entities
    {
        $s = $this->services();
        $entities = new Entities();
        $entities->add($s['resource']);
        $entities->add($s['tv']);
        $entities->add($s['setting']);
        if ($this->has(self::MINISHOP2)) {
            $entities->add(new Entity\Gallery($s['files'], $s['ids'], $this->tmpPath()));
            $entities->add(new Entity\ProductLink($s['links']));
        }
        if ($this->has(self::MSOPTIONSPRICE)) {
            $entities->add(new Entity\Modification($s['mods'], $s['ids']));
        }
        if ($this->has(self::CLIENTCONFIG)) {
            $entities->add($s['clientconfig']);
        }
        foreach ($this->site()['entities'] as $entity) {
            $entities->add($entity);
        }
        return $entities;
    }

    /** Общие объекты: один IdMap и один процессор на процесс */
    private function services(): array
    {
        if ($this->services === null) {
            $m = $this->modx;
            $processor = new Processor($m);
            $tvs = new TemplateVars($m);
            $ids = new IdMap();
            $product = $this->has(self::MINISHOP2) ? new ProductData($m) : null;
            $files = new GalleryFiles($m, $processor);
            $this->services = ['processor' => $processor, 'tvs' => $tvs, 'ids' => $ids, 'files' => $files,
                'resource' => new Entity\Resource($m, $processor, $tvs, $product),
                'tv' => new Entity\Tv($m, $processor, $tvs, $ids), 'setting' => new Entity\Setting($m, $processor),
                'mods' => $this->has(self::MSOPTIONSPRICE) ? new Modifications($m, $processor) : null,
                'clientconfig' => new Entity\ClientConfig($m, $processor), 'links' => new ProductLinks($m),
                'code' => new CodeFiles($m, (int)$m->getOption('mxagenttools.file_max_kb', null, 512))];
            $this->services['selections'] = new Selections($m, (int)$m->getOption('mxagenttools.selection_ttl', null, 86400));
            $this->services['batch'] = new BatchIds($this->services['selections'],
                (int)$m->getOption('mxagenttools.batch_limit', null, 500));
        }
        return $this->services;
    }

    /** Временные файлы загрузок — внутри сайта, под core (не отдаётся по HTTP), не в общем системном /tmp */
    private function tmpPath(): string
    {
        return MODX_CORE_PATH . 'components/mxagenttools/tmp';
    }

    private function corePath(): string
    {
        return (string)$this->modx->getOption('mxagenttools.core_path', null, MODX_CORE_PATH . 'components/mxagenttools/');
    }
}
