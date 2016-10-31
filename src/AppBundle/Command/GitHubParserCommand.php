<?php

namespace AppBundle\Command;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GitHubParserCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('script:github:parse')
            ->setDescription('Find tasks in GitHub organization.')
            ->addArgument(
                'organization',
                InputArgument::REQUIRED,
                'Github organization to look into'
            )
            ->addArgument(
                'search',
                InputArgument::REQUIRED,
                'A resource id.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $token          = $this->getContainer()->getParameter('github_token');
        $exportFolder   = $this->getContainer()->getParameter('export_folder');
        $organization   = $input->getArgument('organization');
        $search         = $input->getArgument('search');
        $now            = new \DateTimeImmutable('now');
        /** @var Client $client */
        $client = $this->getContainer()->get('csa_guzzle.client.crawler_github');

        $response = $client->get('orgs/'.$organization.'/repos?access_token='.$token);
        $repos = json_decode($response->getBody(), true);

        $updates = array();

        foreach ($repos as $repo) {
            try {
                $response = $client->get('search/issues?access_token=' . $token . '&q=' . $search . '+repo:' . $repo['full_name'] . '+updated:>=' . $now->sub(new \DateInterval('P1D'))->format('Y-m-d'));
            } catch (ClientException $e) {
                $output->writeln($e->getMessage());
            }
            $issues = json_decode($response->getBody(), true);

            if (0 === $issues['total_count']) {
                continue;
            }

            $repoEvents = array();

            foreach ($issues['items'] as $issue) {
                $repoEvents[$issue['number']] = array(
                    'title'      => $issue['title'],
                    'body'       => $issue['body'],
                    'url'        => $issue['html_url'],
                    'state'      => $issue['state'],
                    'updated_at' => new \DateTime($issue['updated_at'])
                );
            }

            $updates[$repo['name']] = $repoEvents;
        }

        $file = $exportFolder . $now->format('Y-m-d').'-libretro-github-vita-emulator-related-events.markdown';

        $header = '---'.PHP_EOL;
        $header .= 'layout: post'.PHP_EOL;
        $header .= 'title:  "'.$now->format('F dS').' libretro updates concerning PS Vita emulation and emulators"'.PHP_EOL;
        $header .= 'date:   '.$now->format('Y-m-d H:i:s O').PHP_EOL;
        $header .= 'categories: vita emulation'.PHP_EOL;
        $header .= '---'.PHP_EOL.PHP_EOL;

        file_put_contents($file, $header, FILE_APPEND);

        $content = '';
        foreach ($updates as $repo => $update) {
            if (count($update)) {
                $content .= '### '.$repo . PHP_EOL;
                foreach ($update as $issueId => $event) {
                    $content .= '- [#'.$issueId.']('.$event['url'].') - '.$event['state'].' - '.$event['title'] . ' - ' . $event['updated_at']->format('d/m/Y').PHP_EOL.PHP_EOL;
                    if ('' !== $event['body']) {
                        $content .= $event['body'].PHP_EOL.PHP_EOL;
                    }
                }
            }
        }

        file_put_contents($file, $content, FILE_APPEND);

        return 0;
    }
}
