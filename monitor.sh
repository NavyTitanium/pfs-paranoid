#!/bin/sh

tail -F /var/log/auth.log | while read line; do
  if echo "$line" | grep -Ev 'disconnected|Disconnected' |grep -Eq 'sshd|php-fpm'; then
    # Use awk to perform the operations previously done with read and bash-specific syntax
    eval $(echo "$line" | awk '{
      # Extract date
      date=$1"_"$2"_"$3;

      # Extract service
      service=($5 ~ /sshd/) ? "sshd" : (($5 ~ /php-fpm/) ? "php-fpm" : "unknown");

      # Extract success or failure
      status=($0 ~ /Failed password/ || $0 ~ /Authentication error/ || $0 ~ /authentication error/) ? "Failed" : (($0 ~ /Accepted/ || $0 ~ /Successful login/) ? "Successful" : "unknown");

      # Print extracted variables for sh to evaluate
      printf("date=%s;service=%s;status=%s;", date, service, status);
    }')

    # URL encode the message
    encodedMessage=$(echo "$line" | sed 's/ /%20/g')

    # Execute php-cgi with the extracted and encoded variables
    /usr/local/bin/php-cgi -f /usr/local/www/packages/paranoid/insert.php "action=insert&date=$date&service=$service&status=$status&message=$encodedMessage"
  fi
done