<?php declare(strict_types=1);

namespace Shopware\Docs\Command;

use GuzzleHttp\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SyncDocsCommand extends Command
{
    public const ROOT_CATEGORY_ID = 50;
    public const INITIAL_VERSION = '6.0.0';
    public const DOC_VERSION = '1.0.0';
    public const CATEGORY_SITE_FILENAME = '__categoryInfo';
    private const CREDENTIAL_PATH = __DIR__ . '/wiki.secret';
    private const META_TITLE_PREFIX = 'Shopware Platform: ';
    private $sbpToken;
    private $serverAddress;

    /** @var \GuzzleHttp\Client $client */
    private $client;

    public function __construct()
    {
        parent::__construct();

        if (file_exists(self::CREDENTIAL_PATH)) {
            $credentialsContents = (file_get_contents(self::CREDENTIAL_PATH));
            $credentials = json_decode($credentialsContents, true);
            $this->sbpToken = $credentials['token'];
            $this->serverAddress = $credentials['url'];

            $this->client = new Client(['base_uri' => $this->serverAddress]);
        }
    }

    public function syncFilesWithServer(array $convertedFiles): void
    {
        echo 'Syncing categories ...' . PHP_EOL;
        [$globalCategoryList, $articleList] = $this->gatherCategoryChildrenAndArticles(self::ROOT_CATEGORY_ID, $this->getAllCategories());
        $categoryFiles = [];

        echo 'Deleting ' . count($articleList) . ' old articles ...' . PHP_EOL;
        foreach ($articleList as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
        }

        $i = 0;
        foreach ($convertedFiles as $file => $information) {
            if (strpos($file, self::CATEGORY_SITE_FILENAME) !== false) {
                // we handle category sites below
                $categoryFiles[$file] = $information;
                continue;
            }

            echo 'Syncing file ' . $i . ' ' . $file . '  of ' . count($convertedFiles) . PHP_EOL;
            ++$i;

            if (strpos($file, '_') !== false) {
                continue;
            }

            $fileMetadata = $information['metadata'];
            $html = $information['content'];

            $articleInfo = $this->createLocalizedVersionedArticle($file, $file . '-de');
            $categoryId = $this->getOrCreateMissingCategoryTree($this->getCategoryTreeFromPath($file), $globalCategoryList);
            $this->addArticleToCategory($articleInfo['en_GB'], $categoryId);

            // handle media files
            if (key_exists('media', $fileMetadata)) {
                foreach ($fileMetadata['media'] as $key => $mediaFile) {
                    $mediaLink = $this->uploadMedia($articleInfo['en_GB'], $mediaFile);
                    $html = str_replace($key, $mediaLink, $html);
                }
            }

            $this->updateArticle($articleInfo['en_GB'],
                [
                    'content' => $html,
                    'title' => $fileMetadata['titleEn'],
                    'navigationTitle' => $fileMetadata['titleEn'],
                    'searchableInAllLanguages' => true,
                    'fromProductVersion' => self::INITIAL_VERSION,
                    'active' => true,
                ]
            );

            $this->insertGermanStubArticle($articleInfo['de_DE'], $fileMetadata['titleDe']);
        }

        $oldCategories = $this->getAllCategories();
        $categoryIds = array_column($oldCategories, 'id');

        foreach ($categoryFiles as $file => $information) {
            $categoryPath = $this->getCategoryTreeFromPath($file);
            $parentId = self::ROOT_CATEGORY_ID;
            $categoryId = $parentId;
            $invertedCategoryTree = array_combine(array_values($globalCategoryList), array_keys($globalCategoryList));
            foreach ($categoryPath as $category) {
                $parentId = $categoryId;
                $categoryId = $invertedCategoryTree['/' . $category];
            }

            $fileMetadata = $information['metadata'];
            $html = $information['content'];

            $this->updateCategory($categoryId,
                $parentId,
                $oldCategories[array_search($categoryId, $categoryIds, true)],
                [],
                [
                    'title' => $fileMetadata['titleEn'],
                    'navigationTitle' => $fileMetadata['titleEn'],
                    'content' => $html,
                    'searchableInAllLanguages' => true,
                ],
                [
                    'title' => $fileMetadata['titleDe'],
                    'navigationTitle' => $fileMetadata['titleDe'],
                    'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
                    'searchableInAllLanguages' => false,
                ]
            );
        }
    }

    public function insertGermanStubArticle(array $articleInfoDe, string $title): void
    {
        $this->updateArticle($articleInfoDe, [
            'title' => $title,
            'navigationTitle' => $title,
            'content' => '<p>Die Entwicklerdokumentation ist nur auf Englisch verfügbar.</p>',
            'searchableInAllLanguages' => false,
            'fromProductVersion' => self::INITIAL_VERSION,
            'active' => true,
        ]);
    }

    public function buildArticleVersionUrl(array $articleInfo)
    {
        return vsprintf('/wiki/entries/%d/localizations/%d/versions/%d',
            [
                $articleInfo['articleId'],
                $articleInfo['localeId'],
                $articleInfo['versionId'],
            ]);
    }

    public function getLocalizedVersionedArticle(array $articleInfo): array
    {
        $articleInLocaleWithVersionUrl = $this->buildArticleVersionUrl($articleInfo);
        $response = $this->client->get($articleInLocaleWithVersionUrl, ['headers' => $this->getBasicHeaders()]);
        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true);
    }

    public function getArticle(int $articleId): array
    {
        $response = $this->client->get(
            vsprintf('/wiki/entries/%d', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        );
        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true);
    }

    public function updateArticle(array $articleInfo, array $payload): void
    {
        $currentArticleVersionContents = $this->getLocalizedVersionedArticle($articleInfo);
        $articleInLocaleWithVersionUrl = $this->buildArticleVersionUrl($articleInfo);

        $versionString = self::DOC_VERSION;

        $requiredContents = [
            'id' => $articleInfo['versionId'],
            'version' => $versionString,
            'selectedVersion' => $currentArticleVersionContents,
        ];

        $articleContents = array_merge($currentArticleVersionContents, $requiredContents, $payload);

        $this->client->put(
            $articleInLocaleWithVersionUrl,
            [
                'json' => $articleContents,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    public function uploadMedia(array $articleInfo, string $filePath): string
    {
        $mediaEndpoint = $this->buildArticleVersionUrl($articleInfo) . '/media';

        $body = fopen($filePath, 'rb');
        $response = $this->client->post(
            $mediaEndpoint,
            [
                'multipart' => [
                    [
                        'name' => $filePath,
                        'contents' => $body,
                    ],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();

        return json_decode($responseContents, true)[0]['fileLink'];
    }

    public function getAllCategories(): array
    {
        $response = $this->client->get('/wiki/categories',
            ['headers' => $this->getBasicHeaders()]
        )->getBody()->getContents();

        return json_decode($response, true);
    }

    public function updateCategory(int $categoryId,
                                   int $parentId,
                                   array $oldContents,
                                   array $payloadGlobal,
                                   array $payloadEn,
                                   array $payloadDe): void
    {
        $oldLocalizations = $oldContents['localizations'];
        $oldContentDe = [];
        $oldContentEn = [];
        foreach ($oldLocalizations as $oldLocalization) {
            if ($oldLocalization['locale']['name'] === 'de_DE') {
                $oldContentDe = $oldLocalization;
            } elseif ($oldLocalization['locale']['name'] === 'en_GB') {
                $oldContentEn = $oldLocalization;
            }
        }

        $payloadDe = array_merge($oldContentDe, $payloadDe);

        $payloadEn = array_merge($oldContentEn, $payloadEn);

        $contents = [
            'id' => $categoryId,
            'parent' => ['id' => $parentId],
            'localizations' => [
                $payloadDe,
                $payloadEn,
            ],
        ];

        $contents = array_merge($oldContents, $contents, $payloadGlobal);

        $this->client->put(
            vsprintf('/wiki/categories/%d', [$categoryId]),
            [
                'json' => $contents,
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    public function getBasicHeaders(): array
    {
        return ['X-Shopware-Token' => $this->sbpToken];
    }

    public function addArticleToCategory(array $articleInfo, int $categoryId): void
    {
        $this->client->post(
            vsprintf('/wiki/categories/%s/entries', [$categoryId]),
            [
                'json' => [
                    'id' => $articleInfo['articleId'],
                    'orderPriority' => '',
                    'excludeFromSearch' => false,
                    'categories' => [],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );
    }

    public function getOrCreateMissingCategoryTree($pathList, &$categoryList): int
    {
        $prevEntryId = self::ROOT_CATEGORY_ID;

        $title = '';

        while (count($pathList) !== 0) {
            $entry = array_shift($pathList);
            $title = $title . '/' . $entry;

            $invertedCategoryList = array_combine(array_values($categoryList), array_keys($categoryList));
            if (array_key_exists($title, $invertedCategoryList)) {
                $prevEntryId = $invertedCategoryList[$title];
            } else {
                $prevEntryId = $this->createCategory($title, $title, $title, $title . '-de', $prevEntryId);
                $categoryList[$prevEntryId] = $title;
            }
        }

        return $prevEntryId;
    }

    public function createCategory($titleEn, $seoEn, $titleDe, $seoDe, $parentCategoryId = 50): int
    {
        $response = $this->client->post(
            '/wiki/categories',
            [
                'json' => [
                    'id' => null,
                    'orderPriority' => null,
                    'active' => null,
                    'parent' => ['id' => $parentCategoryId],
                    'localizations' => [
                        [
                            'id' => null,
                            'locale' => [
                                'name' => 'de_DE',
                            ],
                            'title' => $titleDe,
                            'navigationTitle' => $titleDe,
                            'seoUrl' => $seoDe,
                            'content' => '',
                            'metaTitle' => '',
                            'metaDescription' => '',
                            'media' => null,
                            'searchableInAllLanguages' => false,
                        ],
                        [
                            'id' => null,
                            'locale' => [
                                'name' => 'en_GB',
                            ],
                            'title' => $titleEn,
                            'navigationTitle' => $titleEn,
                            'seoUrl' => $seoEn,
                            'content' => '',
                            'metaTitle' => '',
                            'metaDescription' => '',
                            'media' => null,
                            'searchableInAllLanguages' => false,
                        ],
                    ],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $responseJson = json_decode($responseContents, true);

        return $responseJson['id'];
    }

    public function createLocalizedVersionedArticle($seoEn, $seoDe): array
    {
        $response = $this->client->post(
            '/wiki/entries',
            [
                'json' => [
                    'product' => ['id' => 4, 'name' => 'PF', 'label' => 'Shopware Platform'],
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $articleId = json_decode($responseContents, true)['id'];

        // create english lang
        $articleUrl = vsprintf('/wiki/entries/%d', [$articleId]);
        $articleLocalizationUrl = vsprintf('%s/localizations', [$articleUrl]);

        [$localeIdEn, $versionIdEn, $articleUrlEn] = $this->createArticleLocale($seoEn, $articleLocalizationUrl, ['id' => 2, 'name' => 'en_GB']);
        [$localeIdDe, $versionIdDe, $articleUrlDe] = $this->createArticleLocale($seoDe, $articleLocalizationUrl, ['name' => 'de_DE']);

        return [
            'en_GB' => [
                'articleId' => $articleId,
                'localeId' => $localeIdEn,
                'versionId' => $versionIdEn,
            ],
            'de_DE' => [
                'articleId' => $articleId,
                'localeId' => $localeIdDe,
                'versionId' => $versionIdDe,
            ],
        ];
    }

    public function createArticleLocale($seo, $articleLocalizationUrl, $locale): array
    {
        $response = $this->client->post(
            $articleLocalizationUrl,
            [
                'json' => [
                    'locale' => $locale, 'seoUrl' => $seo,
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $localeId = json_decode($responseContents, true)['id'];
        $articleInLocaleUrl = $articleLocalizationUrl . '/' . $localeId;
        $articleVersioningUrl = $articleInLocaleUrl . '/versions';

        // create version
        $response = $this->client->post(
            $articleVersioningUrl,
            [
                'json' => [
                    'version' => '1.0.0',
                ],
                'headers' => $this->getBasicHeaders(),
            ]
        );

        $responseContents = $response->getBody()->getContents();
        $versionId = json_decode($responseContents, true)['id'];
        $articleInLocaleWithVersionUrl = $articleVersioningUrl . '/' . $versionId;

        return [$localeId, $versionId, $articleInLocaleWithVersionUrl];
    }

    public function deleteCategoryChildren(int $categoryId = self::ROOT_CATEGORY_ID): void
    {
        $categories = $this->getAllCategories();

        [$categoriesToDelete, $articlesToDelete] = $this->gatherCategoryChildrenAndArticles(
            $categoryId,
            $categories);

        foreach ($articlesToDelete as $article) {
            $this->disableArticle($article);
            $this->deleteArticle($article);
        }

        foreach (array_keys($categoriesToDelete) as $category) {
            $this->deleteCategory($category);
        }
    }

    public function gatherCategoryChildrenAndArticles($rootId, $categoryJson): array
    {
        $articleList = [];
        $categoryList = [];
        $idStack = [$rootId];

        while (\count($idStack) > 0) {
            $parentId = array_shift($idStack);

            foreach ($categoryJson as $category) {
                $parent = $category['parent'];
                if ($parent !== null && $parent['id'] === $parentId) {
                    $localizations = $category['localizations'];

                    $seo = '';
                    foreach ($localizations as $locale) {
                        if ($locale['locale']['name'] === 'en_GB') {
                            $seo = $locale['seoUrl'];
                        }
                    }
                    $categoryList[$category['id']] = $seo;
                    $articleList[] = $category['entryIds'];
                    $idStack[] = $category['id'];
                }
            }
        }

        $categoryList = array_reverse($categoryList, true);

        // also delete articles in the root category
        $idColumn = array_column($categoryJson, 'id');
        $rootCategoryIndex = array_search($rootId, $idColumn, true);
        if ($rootCategoryIndex !== false) {
            $articleList[] = $categoryJson[$rootCategoryIndex]['entryIds'];
        }

        $articleList = array_merge(...$articleList);
        $articleList = array_unique($articleList);

        return [$categoryList, $articleList];
    }

    public function deleteArticle($articleId): void
    {
        $this->client->delete(
            vsprintf('/wiki/entries/%s', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    public function disableArticle($articleId): void
    {
        $response = $this->client->get(
            vsprintf('/wiki/entries/%s', [$articleId]),
            ['headers' => $this->getBasicHeaders()]
        )->getBody()->getContents();
        $reponseJson = json_decode($response, true);

        if (!key_exists('localizations', $reponseJson) && $reponseJson['localizations'] === null) {
            return;
        }

        foreach ($reponseJson['localizations'] as $locale) {
            $localId = $locale['id'];

            if (!key_exists('versions', $locale) && $locale['versions'] === null) {
                continue;
            }

            foreach ($locale['versions'] as $version) {
                $versionId = $version['id'];
                $this->updateArticle(['articleId' => $articleId, 'localeId' => $localId, 'versionId' => $versionId],
                    ['active' => false]);
            }
        }
    }

    public function deleteCategory($categoryId): void
    {
        $this->client->delete(
            vsprintf('/wiki/categories/%s', [$categoryId]),
            ['headers' => $this->getBasicHeaders()]
        );
    }

    protected function configure(): void
    {
        $this->setName('docs:sync')
            ->addArgument('content', InputArgument::REQUIRED, 'Json encoded contents to sync')
            ->setDescription('Synchronize Wiki');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $jsonContent = $input->getArgument('content');
        $content = json_decode($jsonContent, true);

        $this->syncFilesWithServer($content);
    }

    protected function getCategoryTreeFromPath(string $path): array
    {
        $parts = explode('/', $path);
        $categories = array_slice($parts, 0, count($parts) - 1);

        $categoriesFlat = array_values(array_filter($categories, function (string $value) {
            return !($value === '.' || $value === '..' || strlen($value) === 0);
        }));

        return $categoriesFlat;
    }
}
