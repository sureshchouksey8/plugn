## Set up Docker Dev Environment -1

Build production images 

```bash
docker compose -f docker-compose-prod.yml up --force-recreate
```

Step by step 

Run the following command after installing Docker

```bash
docker-compose up
```

This should set you up with the entire app along with MySQL and Redis. Use the following links to check it out:

* [Backend on localhost:21080](http://localhost:21080)
* [Frontend on localhost:22080](http://localhost:22080)
* [Agent API on localhost:23080](http://localhost:23080)
* [CRM API on localhost:23080](http://localhost:24080)
* [API on localhost:21080](http://localhost:25080)
* [Shortner on localhost:23080](http://localhost:26080)
* [Partner on localhost:23080](http://localhost:27080)
* [Phpmyadmin on localhost:8080](http://localhost:8080)


## Accessing terminal in backend container

```bash
docker-compose exec backend bash

# Now you can run things like
php composer.phar install 
./init
./yii migrate
```

## Running Codeception Tests

Use `docker-compose run --rm` to launch a new backend container which will run the automated tests then destroy the container after it's done.

We have a shortcut script in the project main folder you can use to run complete tests.

```bash
# Shortcut script in project root folder.
# Launch this from your own device(host) not the container
./run-tests.sh

# What this is doing is calling
docker-compose run --rm backend vendor/bin/codecept run --fail-fast --html report-web.html

# You can also run this in the background by passing `-d` flag
# to docker-compose and check the test results in the
# outputted report-web.html
```

## Managing MySQL Database

### Using Terminal / CLI

```bash
# Connect to mysql container
docker-compose exec mysql bash

# Connect to db
mysql -uroot -p12345
```


### Using Phpmyadmin

Phpmyadmin is running on localhost port 8080.

* [http://localhost:8080](http://localhost:8080)
* Username: root
* Password: 12345

## Configure Cron Commands using following intervals

```bash
# Weekly stats report every start of week
0 0 * * SUN /usr/local/bin/php ~/www/yii cron/weekly-report > /dev/null 2>&1

# Retention emails
0 0 * * * /usr/local/bin/php ~/www/yii cron/retention-emails-who-passed-five-days-and-no-sales  > /dev/null 2>&1
0 0 * * * /usr/local/bin/php ~/www/yii cron/retention-emails-who-passed-two-days-and-no-products > /dev/null 2>&1

# Subscription status updates
0 0 * * * /usr/local/bin/php ~/www/yii cron/notify-agents-for-subscription-that-will-expire-soon > /dev/null 2>&1
0 0 * * * /usr/local/bin/php ~/www/yii cron/downgraded-store-subscription > /dev/null 2>&1

# Check site status every minute
* * * * * /usr/local/bin/php ~/www/yii cron/site-status > /dev/null 2>&1

# Update transactions Every 5 minutes
*/5 * * * * /usr/local/bin/php ~/www/yii cron/update-transactions > /dev/null 2>&1

# Every 5 minutes
*/5 * * * * /usr/local/bin/php ~/www/yii cron/send-reminder-email  > /dev/null 2>&1

# Build every 10 sec
* * * * * /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1
* * * * * sleep 10 && /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1
* * * * * sleep 20 && /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1
* * * * * sleep 30 && /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1
* * * * * sleep 40 && /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1
* * * * * sleep 50 && /usr/local/bin/php ~/www/yii cron/create-build-js-file > /dev/null 2>&1

# Every day at midnight
0 0 * * * /usr/local/bin/php ~/www/yii cron/update-voucher-status > /dev/null 2>&1

* * * * * /usr/local/bin/php ~/www/yii cron/create-payment-gateway-account > /dev/null 2>&1
*/5 * * * * /usr/local/bin/php ~/www/yii cron/update-refund-status-message  > /dev/null 2>&1
*/5 * * * * /usr/local/bin/php ~/www/yii cron/make-refund  > /dev/null 2>&1

# Run Cron Commands Once A Day
30 13 * * * /usr/local/bin/php ~/www/yii cron/daily > /dev/null 2>&1

# Run Cron commands every minute
* * * * * /usr/local/bin/php ~/www/yii cron/minute > /dev/null 2>&1
```

## Database schema 

[https://dbdiagram.io/d/6409f34a296d97641d86b825](https://dbdiagram.io/d/6409f34a296d97641d86b825)

## Events 

- Addon Purchase
- Store Created  (with campaign = utm_campaign + utm_medium)
- Premium Plan Purchase
- Tap Charge Attempt
- Order Completed
- Voucher Created

New events to add 

- Agent Signup (with campaign = utm_campaign + utm_medium)
- Item Published
- Order Initiated
- Domain Requests and Domain Request Updated - with request status
- Best Selling (cron based event)
- Item Shared
- Email Opened - with campaign details
- From Campaign - if open link from campaign - can be done by creating link in admin > campaign 
- Refunds Processed

- From Shared Link 
- Return Initiated //not having return process in our system 


- add time in all event?

https://docs.mixpanel.com/docs/data-structure/user-profiles
https://docs.mixpanel.com/docs/quickstart/connect-your-data
https://github.com/signalfx/angular-mixpanel/blob/master/README.md

https://github.com/segmentio/analytics-angular

segment + mixpanel
https://segment.com/docs/connections/spec/identify/

to publish in production

- disable segment to mixpanel flow and config mixpanel from admin 
- upload 
- git pull > ./yii init > ./yii migrate 
- composer require mixpanel.... install mixpanel 
- test config is there in admin as it should be 
- test from console 

`circleci local execute -e SLACK_ACCESS_TOKEN=xoxb-47737144055-4606269551878-bOLPfBq1x0ZvfC4OHbj7WgRP`

## MySql config  

SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''));

To check amount mismatched orders 

------------------

select `order`.order_uuid, `order`.total_price, payment.payment_amount_charged, `order`.store_currency_code, `order`.currency_code,  `order`.currency_rate from payment inner join `order` on `order`.payment_uuid = payment.payment_uuid where payment.payment_amount_charged = `order`.total_price and `order`.store_currency_code != `order`.currency_code and payment_current_status="CAPTURED"

## Spy pixel for campaign 

http://localhost:8888/bawes/plugn/agent/web/v1/store/log-email-campaign/campaign_08617922-5bc3-11ee-aa01-5aa7361ade0b

## menually sync events 

`./yii event/emulate --event="Best Selling"`

### Dealing with bad data accidentally sent to Mixpanel

https://medium.com/product-analytics-academy/dealing-with-bad-data-accidentally-sent-to-mixpanel-a417ecc256ba#:~:text=Important%3A%20Mixpanel%20event%20data%20is,be%20deleted%20from%20your%20project.

Todo
------------------

https://blog.logrocket.com/how-to-run-laravel-docker-compose-ubuntu-v22-04/

https://stackoverflow.com/questions/37741512/how-can-i-use-the-aramex-api-with-wsdl-and-python

 
need to check for option to use netlify ssl/site etc with single source of build output instead of creating separate build 
for each site 
 
//Alter table customer_address modify address_id BIGINT(20) AUTO_INCREMENT 


- list of events + data we passing 
- fire new events to add data in mixpanel 

https://developers.tap.company/reference/create-a-charge

- make sure account on subscription finish, getting invoices for commission 

Experience with Redis or ElasticCache as cache component (as file cache throwing warnings)

https://www.yiiframework.com/extension/yiisoft/yii2-redis/doc/api/2.0/yii-redis-cache

# event list 

https://docs.google.com/spreadsheets/d/14wzSPWjGcJ8h77SxmjiKSxc4rkJiYNITeIISvvRNNnE/edit#gid=1641519238

# ssh 
`eval $(ssh-agent)`
`ssh-add ~/.ssh/github`
ssh path `/home/ubuntu/snap/gh/502/.ssh/id_ed25519.pub`

permission for terminus
`sudo chown -R $(whoami) ./`

# Plugn issue

integrations@tap.company 

## TODO 
- ability to create multiple business accounts? and assign to stores? 
- ability to manage merchants 
- ability to manage documents
https://developers.tap.company/reference/files


https://stripe.com/docs/payments/payment-methods/pmd-registration


## Email Notification POST Parameters

from_email (from address)
 
from_name (from name)
 
env_from (envelope from address - MAIL FROM)
 
env_to_list (list of envelope to addresses - RCPT TO, separated by CRLF)
 
to_list (list of email addresses the email was sent to separated by /r/n)
 
header_list (email headers as HeaderName: HeaderValue separated by /r/n)
 
subject (email subject)
 
body_text (text body content)
 
body_html (html body content)
 
att1_name=attachment_file_name&att1_content=encoded_to_base64_binary_data
 
att2_name=attachment_file_name&att2_content=encoded_to_base64_binary_data
 
You can also receive any custom postback header as described in the previous section.

## apple pay 

### merchant validation endpoint 

`http://api.plugn.io/v2/payment/apple-pay/validate-merchant`

# Tabby 

https://github.com/orgs/tabby-ai/repositories
https://api-docs.tabby.ai/

# SSL renew 

## test 
`sudo certbot renew --dry-run`

## renew 
`sudo certbot renew`

## check 
`sudo certbot certificates`
    

# on reboot, don't forget to run this based on the environment you want to run
 
 - docker-compose -f docker-compose-prod.yml -p plugn-prod-server up -d

 - docker-compose -f docker-compose-dev.yml -p plugn-dev-server up -d

 - docker-compose -f docker-compose-local.yml -p plugn-local-server up -d

# git tag 

git tag -a v2.0 -m "Version 2.0: PHP 7.4 to 8.2"
git push origin v2.0
git tag -l

# fix migration applied but ActiveRecord/ Table column not found error getting trigger 
`docker exec -it plugn-backend-prod /bin/bash`
`./yii cache/flush-schema db`
`./yii cache/flush cache`

## if still not working 

rm -rf /home/ubuntu/plugn/admin/runtime/cache
rm -rf /home/ubuntu/plugn/candidate/runtime/cache
rm -rf /home/ubuntu/plugn/company/runtime/cache
rm -rf /home/ubuntu/plugn/console/runtime/cache
rm -rf /home/ubuntu/plugn/common/runtime/cache
rm -rf /home/ubuntu/plugn/staff/runtime/cache
rm -rf /home/ubuntu/plugn/inspector/runtime/cache
rm -rf /home/ubuntu/plugn/manager/runtime/cache
rm -rf /home/ubuntu/plugn/status/runtime/cache
rm -rf /home/ubuntu/plugn/verification/runtime/cache

# docker container url in local 

## backend 
http://localhost:8083

## store 
http://localhost:8082 

## vendor dashboard 
http://localhost:8081

## crm 
http://localhost:8084

## partner 
http://localhost:8085

## remail 
http://localhost:8086

## shortner 
http://localhost:8087

# MySql requirement 

## for stats 

`SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));`

# to login to docker containers 

## prod server
docker exec -it plugn-backend-prod /bin/bash
docker exec -it plugn-prod-server_mysql_1 /bin/bash
docker exec -it plugn-prod-server_redis_1 /bin/bash

## dev server 
docker exec -it plugn-backend-dev /bin/bash
docker exec -it plugn-dev-server_mysql_1 /bin/bash
docker exec -it plugn-dev-server_redis_1 /bin/bash

### setup database 

`mysql -u root -pplugn;`
`SET GLOBAL sql_mode=(SELECT REPLACE(@@sql_mode, 'ONLY_FULL_GROUP_BY', ''));`

#  Auto-Start SSH Agent on Login

## Edit .bashrc (or .bash_profile for Amazon Linux)

`nano ~/.bashrc`

## Add this at the end of the file:

`if [ -z "$SSH_AUTH_SOCK" ]; then
    eval "$(ssh-agent -s)"
    ssh-add ~/.ssh/github
    ssh-add ~/.ssh/id_rsa
fi`

# Attention 

- need to check if cron is running or not 
- first time running dev server, need to wait for mysql to be ready, then only start the main container 
 

 mysql --host=plugn-main-latest-cluster.cluster-c8mekjvvbygf.eu-west-2.rds.amazonaws.com --user=yo3an --password=iamyo3an yo3an
 
 ## to generate API 

https://apidocjs.com

apidoc -i api -o apidoc

apidoc -i agent -o agent-doc

todo
- api params 
- api method and url compare with main.php 
- add details for passing token 
- warning in doc generation command

# db backup 

- mysqldump --host=plugn-main-latest-cluster.cluster-c8mekjvvbygf.eu-west-2.rds.amazonaws.com --user=yo3an --password=iamyo3an yo3an | gzip > backup.sql.gz
- aws s3 cp ./backup.sql.gz s3://plugn-public-anyone-can-upload-24hr-expiry/backups/backup.sql.gz
- download from s3 

## to install aws 

- sudo snap install aws-cli --classic 