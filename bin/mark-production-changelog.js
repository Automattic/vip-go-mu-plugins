#! /bin/node

// This script is run by GitHub Actions CI

import fetch from 'node-fetch';

const CHANGELOG_BEARER_TOKEN = process.env.CHANGELOG_BEARER_TOKEN;
const CHANGELOG_ENDPOINT = 'https://public-api.wordpress.com/wp/v2/sites/wpvipchangelog.wordpress.com/posts';

const REST_END_PAGE_ERROR = 'rest_post_invalid_page_number';
const RELEASE_CHANNEL_STAGING = 267076;
const RELEASE_CHANNEL_PRODUCTION = 5905;

const fetchPage = async page => {
	const headers = {
		Authorization: `Bearer ${CHANGELOG_BEARER_TOKEN}`,
	};
	const queryArgs = {
		// 'release-channel': RELEASE_CHANNEL_STAGING,
		tags: 1784989, // mu-plugins
		per_page: 100,
		status: 'draft,publish',
		page,
	}

	const argString = new URLSearchParams(queryArgs).toString();

	const result = await fetch(`${CHANGELOG_ENDPOINT}?${argString}`, { headers });
	if (!result.ok) {
		const errMessage = 'Failed to fetch posts';
		try {
			const body = await result.json();
			if (body && body.code === REST_END_PAGE_ERROR) {
				return [];
			}
		} catch (e) {
			console.error('Failed to parse response', e)
		}

		console.error(errMessage, result);
		throw new Error(errMessage)
	}

	return result.json();
};

const updateReleaseChannels = async (id, releaseChannels) => {
	const headers = {
		Authorization: `Bearer ${CHANGELOG_BEARER_TOKEN}`,
		'Content-Type': 'application/json',
	};
	const bodyObj = {
		'release-channel': releaseChannels,
	};
	const body = JSON.stringify(bodyObj);

	await fetch(`${CHANGELOG_ENDPOINT}/${id}`, {
		headers,
		method: "POST",
		body,
	});
};

const main = async () => {
	let pageResult = [];
	let page = 1;

	do {
		pageResult = await fetchPage(page);

		for (const post of pageResult) {
			const { id, status } = post;
			const releaseChannels = post['release-channel'];
			console.log('post', post);

			if (releaseChannels.includes(RELEASE_CHANNEL_PRODUCTION)) {
				continue;
			}

			console.log('Adding prod release schedule for post', id, status, releaseChannels);
			// await updateReleaseChannels(id, [...releaseChannels, RELEASE_CHANNEL_PRODUCTION]);
			console.log('Updated:', id);
		}

		page++;
	// } while (pageResult.length > 0);
	} while (pageResult.length > 10000);
};

main().catch((e) => console.error(e));

