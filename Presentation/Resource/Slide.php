<?php

namespace Cristal\Presentation\Resource;

use Closure;
use Generator;

use Cristal\Presentation\PPTX;
use Cristal\Presentation\ResourceInterface;

class Slide extends XmlResource
{
    /**
     * @var string
     */
    protected const TEMPLATE_SEPARATOR = '.';

    /**
     * @var string
     */
    protected const TABLE_ROW_TEMPLATE_NAME = 'replaceByNewRow';

    /**
     * Slide notes
     * @var NoteSlide
     */
    private $notes = null;

    public function __construct(string $target, string $relType, string $contentType, PPTX $document)
    {
        parent::__construct($target, $relType, $contentType, $document);
        // $this->mapResources();
    }

    /**
     * @param mixed $key
     * @param mixed $data
     * @param string $default
     *
     * @return string
     */
    protected function findDataRecursively($key, $data, $default = ''): string
    {
        foreach (explode(self::TEMPLATE_SEPARATOR, $key) as $segment) {
            if (isset($data[$segment])) {
                $data = $data[$segment];
            } else {
                $data = $default;
            }
        }

        return $data;
    }

    /**
     * Fill data to the slide.
     *
     * @param array|Closure $data
     */
    public function template($data): void
    {
        if (!$data instanceof Closure) {
            $data = function ($matches) use ($data) {
                return $this->findDataRecursively($matches['needle'], $data);
            };
        }

        $xmlString = $this->replaceNeedle($this->getContent(), $data);

        $this->setContent($xmlString);

        $this->save();
    }

    /**
     * Replaces plaheolders (e.g. {{noteSlide}}) in slide notes. If notes do not exist it does not create them. 
     * @param mixed $data
     * @param \Cristal\Presentation\Resource\Slide $slide
     * @return void
     */
    function templateNotes($data){
        
        foreach($this->getResources() as $res){
            if ($res instanceof NoteSlide){
                $this->notes = $res;
                break;
            }
        }
        if ($this->notes){

            if (!$data instanceof Closure) {
                $data = function ($matches) use ($data) {
                    return $this->findDataRecursively($matches['needle'], $data);
                };
            }

            $xmlString = $this->replaceNeedle($this->notes->getContent(), $data); // this changes 

            $this->notes->setContent($xmlString);
            $this->notes->save();
        }
    }

    function createNoteSlide(string $noteContents){
        // TODO: implement
    }
    protected function replaceNeedle(string $source, Closure $callback): string
    {
        $sanitizer = static function ($matches) use ($callback) {
            return htmlspecialchars($callback($matches));
        };

        return preg_replace_callback(
            '/({{)((<(.*?)>)+)?(?P<needle>.*?)((<(.*?)>)+)?(}})/mi',
            $sanitizer,
            $source
        );
    }

    public function table(Closure $data, Closure $finder = null): void
    {
        if (!$finder) {
            $finder = function (string $needle, array $row): string {
                return $this->findDataRecursively($needle, $row);
            };
        }

        $tables = $this->content->xpath('//a:tbl/../../../p:nvGraphicFramePr/p:cNvPr');
        foreach ($tables as $table) {
            $tableId = (string)$table->attributes()['name'];

            // Try to select the second table row which must be the rows to be copied & templated.
            // If the row is not found, it means we only have a header to our table, so we can skip it.
            $tableRow = $this->content->xpath("//p:cNvPr[@name='$tableId']/../..//a:tr")[1] ?? null;
            if (!$tableRow) {
                continue;
            }

            $table = $tableRow->xpath('..')[0];
            $rows = $data($tableId);
            if (!$rows) {
                continue;
            }

            foreach ($rows as $index => $row) {
                $table->addChild(self::TABLE_ROW_TEMPLATE_NAME . $index);
            }

            $xml = preg_replace_callback(
                '/<([^>]+:?' . self::TABLE_ROW_TEMPLATE_NAME . '([\d])+\/>?)/',
                function ($matches) use ($tableRow, $rows, $finder) {
                    [, , $rowId] = $matches;

                    return $this->replaceNeedle($tableRow->asXML(),
                        static function ($matches) use ($rows, $rowId, $finder) {
                            return $finder($matches['needle'], $rows[$rowId]);
                        });
                },
                $this->content->asXML()
            );

            $this->setContent(str_replace($tableRow->asXML(), '', $xml));
        }

        $this->save();
    }

    /**
     * Update the images in the slide.
     *
     * @param array|Closure $data
     */
    public function images($data): void
    {
        if (!$data instanceof Closure) {
            $data = static function ($key) use ($data) {
                return $data[$key] ?? null;
            };
        }

        foreach ($this->getTemplateImages() as $id => $key) {
            if (($content = $data($key)) !== null) {
                $this->getResource($id)->setContent($content);
            }
        }
    }    
    /**
     * Gets the image identifiers capable to being templated.
     */
    public function getTemplateImages(): Generator
    {
        $nodes = $this->content->xpath('//p:pic');

        foreach ($nodes as $node) {
            $id = (string)$node->xpath('p:blipFill/a:blip/@r:embed')[0]->embed;
            $key = $node->xpath('p:nvPicPr/p:cNvPr/@descr');
            if ($key && isset($key[0]) && !empty($key[0]->descr)) {
                yield $id => (string)$key[0]->descr;
            }
        }
    }

    protected function mapResources(): void
    {        
        parent::mapResources();
        // var_dump(count($this->resources));
        // find the notes for this slide (there should be only one resource of type NoteSlide)        
        foreach($this->resources as $res){
            if ($res instanceof NoteSlide){
                $this->notes = $res;
                break;
            }
        }
        // Ignore noteSlide prevent failure because, current library doesnt support that, for moment...
        /* as of 2024.10.27 the NoteSlide seems to be working fine
        $this->resources = array_filter($this->resources, static function ($resource) {
            return !$resource instanceof NoteSlide;
        }); */
    }

    /**
     * Summary of getNotes
     * @return GenericResource|NoteSlide
     */
    public function getNotes(): ?NoteSlide{
        return $this->notes;
    }    
}
