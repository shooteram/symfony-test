<?php

namespace App\DataFixtures;

use App\Entity\Topic;
use Goutte\Client as Goutte;
use GuzzleHttp\Client as Guzzle;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DomCrawler\Crawler;

class TopicFixtures extends Fixture
{
    public function load(ObjectManager $manager)
    {
        $this->manager = $manager;

        $goutte = new Goutte(['headers' => ['accept' => 'text/html']]);
        $guzzle = new Guzzle(['timeout' => 60]);
        $goutte->setClient($guzzle);

        $crawler = $goutte->request('GET', $this->getUri())->html();
        $crawler = new Crawler($crawler);
        $this->retrieveFields($crawler);

        $after = $crawler->filter('input[name="after"]')->attr('value');
        if ($after) {
            $crawler = $goutte->request('GET', $this->getUri(['after' => $after]))->html();
            $crawler = new Crawler($crawler);

            $this->retrieveFields($crawler);
        }
    }
    
    private function getUri(? array $queries = []): string
    {
        $uri = "https://github.com/topics";
        $queries = array_merge($queries, ['utf8' => '✓']);

        if ($queries) {
            $uri = $uri . '?';

            foreach (array_keys($queries) as $query) {
                $uri .= $query . '=' . $queries[$query] . '&';
            }

            $uri = substr($uri, 0, -1);
        }

        return $uri;
    }

    private function retrieveFields(Crawler $crawler): void
    {
        $crawler->filter('li.py-4.border-bottom')->each(function ($node) {
            $name = $node->filter('p.f3')->each(function ($node) {
                return $node->text();
            });
            $name = count($name) > 0 ? $name[0] : null;

            $link_to_github = $node->filter('a.d-flex.no-underline')->each(function ($node) {
                return "https://github.com{$node->attr('href')}";
            });
            if (!$link_to_github) {
               echo "No link to Github found for topic \"{$name}\"." . PHP_EOL;
            }
            $link_to_github = count($link_to_github) > 0 ? $link_to_github[0] : null;

            $description = $node->filter('p.f5')->each(function ($node) {
                return $node->text();
            });
            if (!$description) {
               echo "No description found for topic \"{$name}\"." . PHP_EOL;
            }
            $description = count($description) > 0 ? $description[0] : null;

            $image = $node->filter('img')->each(function ($node) {
                return $node->attr('src');
            });
            if (!$image) {
               echo "No image found for topic \"{$name}\"." . PHP_EOL;
            }
            $image = count($image) > 0 ? $image[0] : null;


            $data = (object)[
                'name' => $name,
                'link_to_github' => $link_to_github,
                'description' => $description,
                'image' => $image,
            ];

            $this->createTopic($data);
        });

        $this->manager->flush();
    }

    private function createTopic(object $topic): void
    {
        $topic = (new Topic())->setName($topic->name)
            ->setDescription($topic->description)
            ->setImage($topic->image)
            ->setGithubLink($topic->link_to_github);

        $this->manager->persist($topic);
    }
}
