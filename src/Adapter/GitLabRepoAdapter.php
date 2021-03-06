<?php

/**
 * This file is part of Gush.
 *
 * (c) Luis Cordova <cordoval@gmail.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Gush\Adapter;

use Gitlab\Client;
use Gitlab\Model;
use Gush\Exception;
use Gush\Model\Issue;
use Gush\Model\MergeRequest;

/**
 * @author Luis Cordova <cordoval@gmail.com>
 * @author Aaron Scherer <aequasi@gmail.com>
 * @author Julien Bianchi <contact@jubianchi.fr>
 */
class GitLabRepoAdapter extends BaseAdapter
{
    use GitLabAdapter;

    public function supportsRepository($remoteUrl)
    {
        return false !== stripos($remoteUrl, parse_url($this->configuration['repo_domain_url'])['host']);
    }

    /**
     * {@inheritdoc}
     */
    public function createFork($org)
    {
        throw new Exception\UnsuportedOperationException('Forking is not supported by Gitlab');
    }

    public function getPullRequestUrl($id)
    {
        return sprintf(
            '%s/%s/%s/merge_requests/%d',
            $this->configuration['repo_domain_url'],
            $this->getUsername(),
            $this->getRepository(),
            $this->getPullRequest($id)['iid']
        );
    }

    /**
     * {@inheritdoc}
     */
    public function createComment($id, $message)
    {
        $issue = MergeRequest::fromArray(
            $this->client,
            $this->getCurrentProject(),
            $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id)
        );
        $comment = $issue->addComment($message);

        return sprintf('%s#note_%d', $this->getPullRequestUrl($id), $comment->id);
    }

    /**
     * {@inheritdoc}
     */
    public function getComments($id)
    {
        $issue = MergeRequest::fromArray(
            $this->client,
            $this->getCurrentProject(),
            $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id)
        );

        return $issue->showComments();
    }

    /**
     * {@inheritdoc}
     */
    public function getLabels()
    {
        throw new Exception\UnsuportedOperationException('Labels are not supported by Gitlab');
    }

    /**
     * {@inheritdoc}
     */
    public function getMilestones(array $parameters = [])
    {
        return $this->client->api('milestones')->all($this->getCurrentProject()->id);
    }

    /**
     * {@inheritdoc}
     */
    public function updatePullRequest($id, array $parameters)
    {
        $issue = $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id);
        $issue = Issue::fromArray($this->client, $this->getCurrentProject(), $issue);

        if (isset($parameters['assignee'])) {
            $assignee = $this->client->api('users')->search($parameters['assignee']);

            if (sizeof($assignee) === 0) {
                throw new \InvalidArgumentException(sprintf('Could not find user %s', $parameters['assignee']));
            }

            $issue->update([
                'assignee_id' => current($assignee)['id']
            ]);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function closePullRequest($id)
    {
        $mr = MergeRequest::fromArray($this->client, $this->getCurrentProject(), $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id));

        return $mr->close()->id;
    }

    /**
     * {@inheritdoc}
     */
    public function openPullRequest($base, $head, $subject, $body, array $parameters = [])
    {
        $head = explode(':', $head);
        $mr = $this->getCurrentProject()->createMergeRequest(
            $head[1],
            $base,
            $subject,
            null,
            $body
        );

        return array(
            'html_url' => $this->getPullRequestUrl($mr->id),
            'number' => $mr->iid
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPullRequest($id)
    {
        return MergeRequest::fromArray($this->client, $this->getCurrentProject(), $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id))->toArray();
    }

    /**
     * {@inheritdoc}
     */
    public function getPullRequestCommits($id)
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function mergePullRequest($id, $message)
    {
        $mr = $this->client->api('merge_requests')->show($this->getCurrentProject()->id, $id);
        $mr = MergeRequest::fromArray($this->client, $this->getCurrentProject(), $mr);
        $mr->merge($message);
    }

    /**
     * {@inheritdoc}
     */
    public function getPullRequests($state = null, $page = 1, $perPage = 30)
    {
        $mergeRequests = $this->client->api('merge_requests')->all($this->getCurrentProject()->id);

        if (null !== $state) {
            $mergeRequests = array_filter($mergeRequests, function ($mr) use ($state) { return $mr['state'] === $state; });
        }

        return array_map(
            function ($mr) {
                return MergeRequest::fromArray($this->client, $this->getCurrentProject(), $mr)->toArray();
            },
            $mergeRequests
        );
    }

    /**
     * {@inheritdoc}
     */
    public function getPullRequestStates()
    {
        return ['opened', 'closed', 'merged'];
    }

    /**
     * {@inheritdoc}
     */
    public function createRelease($name, array $parameters = [])
    {
        throw new Exception\UnsuportedOperationException('Releases are not supported by Gitlab');
    }

    /**
     * {@inheritdoc}
     */
    public function getReleases()
    {
        throw new Exception\UnsuportedOperationException('Releases are not supported by Gitlab');
    }

    /**
     * {@inheritdoc}
     */
    public function removeRelease($id)
    {
        throw new Exception\UnsuportedOperationException('Releases are not supported by Gitlab');
    }

    /**
     * {@inheritdoc}
     */
    public function createReleaseAssets($id, $name, $contentType, $content)
    {
        throw new Exception\UnsuportedOperationException('Releases are not supported by Gitlab');
    }
}
