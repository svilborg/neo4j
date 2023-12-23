<?php
require __DIR__ . '/../vendor/autoload.php';

use Laudis\Neo4j\ClientBuilder;
use Laudis\Neo4j\Databags\Statement;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;

$app = new SingleCommandApplication();

$app->setName('Test')
    ->addOption('check')
    ->addOption('import')
    ->setCode(function (InputInterface $input, OutputInterface $output) {

        $client = ClientBuilder::create()
            ->withDriver('bolt', 'bolt://neo4j:test12345678@neo4j')
            ->withDefaultDriver('bolt')
            ->build();


        if ($input->getOption('check')) {
            $output->writeln('Connected : ' . ($client->verifyConnectivity() ? 'true' : 'false'));
            return;
        }


        if ($input->getOption('import')) {

            foreach (getData() as $statementText) {
                $statement = Statement::create($statementText);

                $output->writeln('================');
                $output->writeln($statement->getText());

                $result = $client->runStatement($statement);
            }

            $output->writeln('================');
            $output->writeln('Imported.');

        }

    });

$app->run();

function getData() {
    return [


// Truncate
        'MATCH (n) DETACH DELETE n;',

        'DROP INDEX  userIdIndex IF EXISTS;',
        'DROP INDEX  threadIdIndex IF EXISTS;',
        'DROP INDEX  commentIdIndex IF EXISTS;',
        'DROP INDEX  merchantIdIndex IF EXISTS;',
        'DROP INDEX  groupIdIndex IF EXISTS;',

// Nodes

        "LOAD CSV FROM 'file:///ds_users.csv' AS line CREATE (u:User {name: line[1], userId: toInteger(line[0])});",

        "LOAD CSV FROM 'file:///ds_threads.csv' as line
CREATE(t: Thread {
        threadId: toInteger(line[0]),
        name: line[1],
        merchantId: toInteger(line[2]),
        price : toFloat(line[3]),
        thread_type_id: toInteger(line[4]),
        status: line[5]
});",

        "LOAD CSV FROM 'file:///ds_comments.csv' as line
CREATE(c: Comment {name: substring(line[5], 0, 20), commentId: toInteger(line[0])});",

        "LOAD CSV FROM 'file:///ds_merchants.csv' as line
CREATE(m: Merchant {name: line[2], merchantId: toInteger(line[0])});",

        "LOAD CSV FROM 'file:///ds_thread_groups.csv' as line
CREATE(g: Group {name: line[1], groupId: toInteger(line[0])});",


// Indices

        "CREATE INDEX userIdIndex for (u:User)
ON(u . userId);",

        "CREATE INDEX threadIdIndex for (t:Thread)
ON(t . threadId);",

        "CREATE INDEX commentIdIndex for (c:Comment)
ON(c . commentId);
",
        "CREATE INDEX merchantIdIndex for (m:Merchant)
ON(m . merchantId);
",
        "CREATE INDEX groupIdIndex for (g:Group)
ON(g . groupId);",

// Relations

        "LOAD CSV FROM 'file:///ds_threads.csv' as line
match (m:Merchant {
                        merchantId:
                        toInteger(line[12])})
match (t:Thread {
                        threadId:
                        toInteger(line[0])})
MERGE(m) - [:SELL]->(t)
return *;",

//// Thread Groups

        "LOAD CSV FROM 'file:///ds_thread_group_assignments.csv' as line
match (t:Thread {
                        threadId:
                        toInteger(line[0])})
match (g:Group {
                        groupId:
                        toInteger(line[1])})
MERGE(t) - [:IS_IN]->(g)
return *;
",

//// User Comments

        "LOAD CSV FROM 'file:///ds_comments.csv' as line
match (c:Comment {
                        commentId:
                        toInteger(line[0])})
match (u:User {
                        userId:
                        toInteger(line[1])})
MERGE(u) - [p:POST]->(c)
return *;",

//// Thread Comments
        "LOAD CSV FROM 'file:///ds_comments.csv' as line
match (c:Comment {
                        commentId:
                        toInteger(line[0])})
match (t:Thread {
                        threadId:
                        toInteger(line[2])})
MERGE(c) - [:IS_ON]->(t)
return *;",

////// User Thread Comments

        "LOAD CSV FROM 'file:///ds_comments.csv' as line
match (u:User {
                        userId:
                        toInteger(line[1])})
match (t:Thread {
                        threadId:
                        toInteger(line[2])})
MERGE(u) - [r:COMMENT_ON_TEMP {
                        weight:
                        1}]->(t)
ON CREATE SET r . weight = 1
ON MATCH SET r . weight = r . weight + 1
return *;",

        "match (u:User)-[uc:COMMENT_ON_TEMP]->(t:Thread)
WITH u, COLLECT(uc) as oldRels, t, COUNT(uc) as weight
foreach (r IN oldRels | DELETE r)
WITH u, weight, t
CREATE(u) - [uc:COMMENT {
                        weight:
                        weight}]->(t);",

//// User Votes

        "LOAD CSV FROM 'file:///ds_thread_votes.csv' as line
match (t:Thread { threadId: toInteger(line[1]) })
match (u:User {userId: toInteger(line[2]) })
MERGE(u) - [:VOTE { dir : line[3] }]->(t)
return *;",

        'MATCH (u:User )-[r:VOTE]->(t:Thread)
WHERE r.dir = "up"
WITH u,t    
CREATE (u)-[r:VOTE_UP]->(t);',

        'MATCH (u:User )-[r:VOTE]->(t:Thread)
WHERE r.dir = "down"
WITH u,t    
CREATE (u)-[r:VOTE_DOWN]->(t);',

        'MATCH()-[r:VOTE]->() DELETE r;',

    ];
}
