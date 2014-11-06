<?php
	
require 'vendor/autoload.php';	
	
use GuzzleHttp\Client;
	
$simple_key = 'DNSSIMPLE API KEY HERE';
$linode_key = 'LINODE API KEY HERE';
$ll_domains = [];

// Get linode domains.
$client = new Client();
$response = $client->post('https://api.linode.com/?api_key=' . $linode_key . '&api_action=domain.list');

foreach($response->json()['DATA'] AS $key => $row)
{
	$ll_domains[] = $row['DOMAIN'];
}

$client = new Client();

$response = $client->get('https://api.dnsimple.com/v1/domains', [
	'headers' => [ 'X-DNSimple-Token' => "spicer@cloudmanic.com:$simple_key" ]
]);

$domains = $response->json();

// Loop through the different domains.
foreach($domains AS $key => $row)
{
	$id = $row['domain']['id'];
	
	// If this domain is already at Linode we skip it.
	if(in_array($row['domain']['name'], $ll_domains))
	{
		echo "Skipping " . $row['domain']['name'] . "\n";
		continue;
	} else
	{
		echo "Importing " . $row['domain']['name'] . "\n";		
	}
	
	// Create domain at Linode.
	$response = $client->post('https://api.linode.com/?api_key=' . $linode_key . '&api_action=domain.create', [
		'body' => [
			'Domain' => $row['domain']['name'],
			'Type' => 'master',
			'SOA_Email' => 'support@cloudmanic.com'
		]	
	]);	
	
	$ll_id = $response->json()['DATA']['DomainID'];
	
	// Get records for domain
	$response = $client->get('https://api.dnsimple.com/v1/domains/' . $id . '/records', [
		'headers' => [ 'X-DNSimple-Token' => "spicer@cloudmanic.com:$simple_key" ]
	]);

	$records = $response->json();	
	
	foreach($records AS $key2 => $row2)
	{
		if($row2['record']['record_type'] == 'NS')
		{
			continue;
		}
		
		$response = $client->post('https://api.linode.com/?api_key=' . $linode_key . '&api_action=domain.resource.create', [
			'body' => [
				'DomainID' => $ll_id,
				'Type' => $row2['record']['record_type'],
				'Name' => $row2['record']['name'],
				'Target' => $row2['record']['content']
			]	
		]);				
	}
}