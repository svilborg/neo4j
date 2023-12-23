
// Truncate
MATCH (n)
DETACH DELETE n;

DROP INDEX  userIdIndex IF EXISTS;
DROP INDEX  threadIdIndex IF EXISTS;
DROP INDEX  commentIdIndex IF EXISTS;
DROP INDEX  merchantIdIndex IF EXISTS;
DROP INDEX  groupIdIndex IF EXISTS;

// Nodes

LOAD CSV FROM 'file:///ds_users.csv' AS line
CREATE (u:User {name: line[1], userId: toInteger(line[0])});

LOAD CSV FROM 'file:///ds_threads.csv' AS line
CREATE (t:Thread {
        name: line[1],
        threadId: toInteger(line[0]),
        merchantId: toInteger(line[14]),
        price : toFloat(line[3]),
        thread_type_id: toInteger(line[27]),
        status: line[15]
});

LOAD CSV FROM 'file:///ds_comments.csv' AS line
CREATE (c:Comment {name: substring(line[5], 0, 20), commentId: toInteger(line[0])});

LOAD CSV FROM 'file:///ds_merchants.csv' AS line
CREATE (m:Merchant {name: line[1], merchantId: toInteger(line[0])});

LOAD CSV FROM 'file:///ds_thread_groups.csv' AS line
CREATE (g:Group {name: line[1], groupId: toInteger(line[0])});


// Indices

CREATE INDEX userIdIndex FOR (u:User)
ON (u.userId);

CREATE INDEX threadIdIndex FOR (t:Thread)
ON (t.threadId);

CREATE INDEX commentIdIndex FOR (c:Comment)
ON (c.commentId);

CREATE INDEX merchantIdIndex FOR (m:Merchant)
ON (m.merchantId);

CREATE INDEX groupIdIndex FOR (g:Group)
ON (g.groupId);

// Relations

LOAD CSV FROM 'file:///ds_threads.csv' AS line
MATCH (m:Merchant {merchantId: toInteger(line[12])})
MATCH (t:Thread {threadId: toInteger(line[0])})
MERGE (m)-[:SELL]->(t)
RETURN *;

//// Thread Groups

LOAD CSV FROM 'file:///ds_thread_group_assignments.csv' AS line
MATCH (t:Thread {threadId: toInteger(line[0])})
MATCH (g:Group {groupId: toInteger(line[1])})
MERGE (t)-[:IS_IN]->(g)
RETURN *;


//// User Comments

LOAD CSV FROM 'file:///ds_comments.csv' AS line
MATCH (c:Comment {commentId: toInteger(line[0])})
MATCH (u:User {userId: toInteger(line[1])})
MERGE (u)-[p:POST]->(c)
RETURN *;

//// Thread Comments
LOAD CSV FROM 'file:///ds_comments.csv' AS line
MATCH (c:Comment {commentId: toInteger(line[0])})
MATCH (t:Thread {threadId: toInteger(line[2])})
MERGE (c)-[:IS_ON]->(t)
RETURN *;

//// User Thread Comments

LOAD CSV FROM 'file:///ds_comments.csv' AS line
MATCH (u:User {userId: toInteger(line[1])})
MATCH (t:Thread {threadId: toInteger(line[2])})
MERGE (u)-[r:COMMENT_ON_TEMP {weight:1}]->(t)
ON CREATE SET r.weight = 1
ON MATCH SET r.weight = r.weight + 1
RETURN *;

MATCH (u:User)-[uc:COMMENT_ON_TEMP]->(t:Thread)
WITH u, COLLECT(uc) as oldRels, t, COUNT(uc) as weight
FOREACH(r IN oldRels | DELETE r)
WITH u, weight, t
CREATE (u)-[uc:COMMENT {weight:weight}]->(t);

//// User Votes

LOAD CSV FROM 'file:///ds_thread_votes.csv' AS line
MATCH (t:Thread {threadId: toInteger(line[1])})
MATCH (u:User {userId: toInteger(line[2])})
MERGE (u)-[:VOTE {dir : line[3] }]->(t)
RETURN *;

MATCH (u:User )-[r:VOTE]->(t:Thread)
WHERE r.dir = "down"
WITH u,t
CREATE (u)-[r:VOTE_UP]->(t);

MATCH (u:User )-[r:VOTE]->(t:Thread)
WHERE r.dir = "down"
WITH u,t
CREATE (u)-[r:VOTE_DOWN]->(t);

MATCH()-[r:VOTE]->() DELETE r;
