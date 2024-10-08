<?php 

namespace Cristal\Presentation\Tests;

use Cristal\Presentation\PPTX;

class NoteSlideTest extends TestCase
{
    private $data = [
        "noteSlide1" => "Notes for slide1",
        "noteSlide2" => "Notes for slide2",
        "noteSlide3" => "Notes for slide3"
    ];

    /**
     * @var PPTX
     */
    protected $pptx;

    public function setUp(): void
    {
        parent::setUp();
        $this->pptx = new PPTX(__DIR__.'/mock/powerpoint.pptx');
    }

    /**
     * Summary of it_templates_note_slide
     * @test
     */
    public function it_templates_note_slide(){
        
        foreach($this->pptx->getSlides() as $slide){
            $slide->templateNotes($this->data);
        }

        $this->pptx->saveAs(self::TMP_PATH.'/template.pptx');

        $templatedPPTX = new PPTX(self::TMP_PATH.'/template.pptx');                

        $templatedSlide = $templatedPPTX->getSlides()[0];
        $templatedSlide->getResources(); // need to call this so the resources are properly mapped ??
        $this->assertStringContainsString($this->data['noteSlide1'], $templatedSlide->getNotes()->getContent());
        
    }
}
