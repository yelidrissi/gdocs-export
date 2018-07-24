FROM  dergachev/gdocs-export:latest

RUN apt-get update && apt-get install -y dialog apt-utils apache2 php5 php5-curl nano
RUN a2enmod rewrite && php5enmod curl
RUN echo 'debconf debconf/frontend select Noninteractive' | debconf-set-selections

# RUN echo 'LC_ALL="en_US.UTF-8"' > /etc/default/locale
# Set locale variables
RUN apt-get install -y locales
RUN locale-gen en_US en_US.UTF-8
RUN dpkg-reconfigure locales

#### RVM
# RUN apt-get install software-properties-common -y
# RUN apt-add-repository -y ppa:rael-gc/rvm -y
# RUN apt-get update -y
# RUN apt-get install rvm -y



############################################################
# Gdocs export server dependencies (Apache, PHP)
############################################################
RUN ["useradd","-m","-g","www-data","gdocs"]
# Update the default apache site with the config we created.
COPY server/apache-config.conf /etc/apache2/sites-available/000-default.conf
COPY server/envvars /etc/apache2/envvars.bak
RUN cat /etc/apache2/envvars.bak | tr -s '\r' '\n' > /etc/apache2/envvars
RUN ["/bin/bash","-c","source /etc/apache2/envvars"]





RUN service apache2 restart

# By default start up apache in the foreground, override with /bin/bash for interative.
CMD apachectl -D FOREGROUND

# allow gdocs to run script as root
RUN printf "\ngdocs ALL=(root) NOPASSWD: /var/gdocs-export/server/scripts/web-convert-gdoc.sh\n" >> /etc/sudoers

EXPOSE 12736
WORKDIR /var/gdocs-export/

ARG extra
RUN $extra