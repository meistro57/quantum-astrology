<?php
use PHPUnit\Framework\TestCase;
use QuantumAstrology\Charts\ChartService;
use QuantumAstrology\Charts\TransitTimeline;
use QuantumAstrology\Charts\Chart;

class SmokeTest extends TestCase
{
    public function testChartServiceExists()
    {
        // This confirms the class can be loaded and autoloader is working
        $this->assertTrue(class_exists(ChartService::class));
    }

    public function testTransitTimelineInstantiatable()
    {
        // We can't easily test the full logic without a DB, 
        // but we can check if the Class exists and has the method.
        $this->assertTrue(class_exists(TransitTimeline::class));
        $this->assertTrue(method_exists(TransitTimeline::class, 'calculateSeries'));
    }

    public function testChartClassStructure()
    {
        $this->assertTrue(class_exists(Chart::class));
        
        // Ensure our static methods are callable
        $this->assertTrue(method_exists(Chart::class, 'create'));
        $this->assertTrue(method_exists(Chart::class, 'findById'));
    }
}
