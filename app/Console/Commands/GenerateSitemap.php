<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Video;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;

class GenerateSitemap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sitemap:generate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate the sitemap.xml file';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Generating sitemap...');

        $sitemap = $this->generateSitemapXml();
        
        File::put(public_path('sitemap.xml'), $sitemap);
        
        $this->info('Sitemap has been generated at: ' . public_path('sitemap.xml'));
        return 0;
    }

    /**
     * Generate the sitemap XML content
     *
     * @return string
     */
    protected function generateSitemapXml(): string
    {
        $sitemap = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $sitemap .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        // Add static pages
        $sitemap .= $this->addUrl(url('/'), '1.0', 'daily');
        $sitemap .= $this->addUrl(url('/browse'), '0.9', 'daily');
        $sitemap .= $this->addUrl(url('/categories'), '0.8', 'weekly');
        $sitemap .= $this->addUrl(url('/about'), '0.5', 'monthly');
        $sitemap .= $this->addUrl(url('/contact'), '0.5', 'monthly');
        $sitemap .= $this->addUrl(url('/terms'), '0.3', 'monthly');
        $sitemap .= $this->addUrl(url('/privacy'), '0.3', 'monthly');

        // Add categories
        $categories = Category::all();
        foreach ($categories as $category) {
            $sitemap .= $this->addUrl(
                url('/categories/' . $category->id),
                '0.7',
                'weekly',
                $category->updated_at
            );
        }

        // Add published videos
        $videos = Video::where('status', 'complete')
            ->orderBy('created_at', 'desc')
            ->limit(1000) // Limit to avoid huge sitemaps
            ->get();
            
        foreach ($videos as $video) {
            $sitemap .= $this->addUrl(
                url('/videos/' . $video->id),
                '0.8', 
                'daily',
                $video->updated_at
            );
        }

        $sitemap .= '</urlset>';
        return $sitemap;
    }

    /**
     * Generate a URL entry for the sitemap
     *
     * @param string $url
     * @param string $priority
     * @param string $changefreq
     * @param Carbon|null $lastmod
     * @return string
     */
    protected function addUrl(string $url, string $priority, string $changefreq, ?Carbon $lastmod = null): string
    {
        $url = '<url>' . PHP_EOL;
        $url .= '  <loc>' . $url . '</loc>' . PHP_EOL;
        
        if ($lastmod) {
            $url .= '  <lastmod>' . $lastmod->format('Y-m-d\TH:i:sP') . '</lastmod>' . PHP_EOL;
        }
        
        $url .= '  <changefreq>' . $changefreq . '</changefreq>' . PHP_EOL;
        $url .= '  <priority>' . $priority . '</priority>' . PHP_EOL;
        $url .= '</url>' . PHP_EOL;
        
        return $url;
    }
} 