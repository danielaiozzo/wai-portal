FROM alpine:latest

LABEL maintainer="Davide Porrovecchio <davide.porrovecchio@agid.gov.it>"

RUN apk --update add curl && rm /var/cache/apk/*

RUN mkdir -p /opt/matomo
ARG MATOMO_VERSION
ARG MATOMO_WAI_THEME_VERSION
ARG MATOMO_PLUGIN_DISABLESITETRACKING_VERSION
ARG MATOMO_PLUGIN_QUEUEDTRACKING_VERSION
ARG MATOMO_PLUGIN_PROTECTTRACKID_VERSION
ARG MATOMO_PLUGIN_CUSTOMDIMENSIONS_VERSION
ARG MATOMO_PLUGIN_LOGINFILTERIP_VERSION
ARG MATOMO_PLUGIN_TRACKERHOSTNAME_VERSION
ARG MATOMO_INGESTION_HOSTNAME

RUN curl -fsSL -o matomo.tar.gz \
        "https://builds.matomo.org/matomo-${MATOMO_VERSION}.tar.gz" \
    && tar -xzf matomo.tar.gz -C /opt/matomo --strip-components=1 \
    && rm matomo.tar.gz

COPY ./config.ini.php /opt/matomo/config/config.ini.php
COPY ./matomo.sql /opt/matomo-install/matomo.sql
COPY ./install_matomo_db.sh /opt/matomo-install/install_matomo_db.sh
COPY ./install_plugins_db.sh /opt/matomo-install/install_plugins_db.sh
COPY ./plugins-sql/* /opt/matomo-install/plugins/

RUN curl -fsSL -o DBIP-City.mmdb.gz \
        "https://download.db-ip.com/free/dbip-city-lite-`date +%Y-%m`.mmdb.gz" \
    && gunzip DBIP-City.mmdb.gz \
    && mv DBIP-City.mmdb /opt/matomo/misc/

RUN curl -fsSL -o WAIMatomoTheme.tar.gz \
        "https://github.com/agid/wai-matomo-theme/releases/download/${MATOMO_WAI_THEME_VERSION}/wai-matomo-theme_${MATOMO_WAI_THEME_VERSION}_auto_activate.tar.gz" \
    && tar -xf WAIMatomoTheme.tar.gz -C /opt/matomo/plugins \
    && rm -rf WAIMatomoTheme.tar.gz

RUN if [ -n "$MATOMO_PLUGIN_DISABLESITETRACKING_VERSION" ]; then \
    curl -fsSL -o DisableTracking.tar.gz \
        "https://github.com/AgID/wai-matomo-plugin-DisableTracking/releases/download/${MATOMO_PLUGIN_DISABLESITETRACKING_VERSION}/DisableTracking.tar.gz" \
    && tar -xf DisableTracking.tar.gz -C /opt/matomo/plugins \
    && rm -rf DisableTracking.tar.gz \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "DisableTracking"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "DisableTracking"\n\1/g' /opt/matomo/config/config.ini.php \
    && echo "mysql -u root -p@DB_PASSWORD@ < /opt/matomo-install/plugins/disable_tracking.sql" >> /opt/matomo-install/install_plugins_db.sh \
;fi

RUN if [ -n "$MATOMO_PLUGIN_QUEUEDTRACKING_VERSION" ]; then \
    curl -fsSL -o QueuedTracking.zip \
        "https://plugins.matomo.org/api/2.0/plugins/QueuedTracking/download/${MATOMO_PLUGIN_QUEUEDTRACKING_VERSION}" \
    && unzip QueuedTracking.zip -d /opt/matomo/plugins \
    && rm -rf QueuedTracking.zip \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "QueuedTracking"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "QueuedTracking"\n\1/g' /opt/matomo/config/config.ini.php \
    && echo "mysql -u root -p@DB_PASSWORD@ < /opt/matomo-install/plugins/queued_tracking.sql" >> /opt/matomo-install/install_plugins_db.sh \
;fi

RUN if [ -n "$MATOMO_PLUGIN_PROTECTTRACKID_VERSION" ]; then \
    curl -fsSL -o ProtectTrackID.zip \
        "https://plugins.matomo.org/api/2.0/plugins/ProtectTrackID/download/${MATOMO_PLUGIN_PROTECTTRACKID_VERSION}" \
    && unzip ProtectTrackID.zip -d /opt/matomo/plugins \
    && rm -rf ProtectTrackID.zip \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "ProtectTrackID"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "ProtectTrackID"\n\1/g' /opt/matomo/config/config.ini.php \
;fi

RUN if [ -n "$MATOMO_PLUGIN_CUSTOMDIMENSIONS_VERSION" ]; then \
    curl -fsSL -o CustomDimensions.zip \
        "https://plugins.matomo.org/api/2.0/plugins/CustomDimensions/download/${MATOMO_PLUGIN_CUSTOMDIMENSIONS_VERSION}" \
    && unzip CustomDimensions.zip -d /opt/matomo/plugins \
    && rm -rf CustomDimensions.zip \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "CustomDimensions"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "CustomDimensions"\n\1/g' /opt/matomo/config/config.ini.php \
    && echo "mysql -u root -p@DB_PASSWORD@ < /opt/matomo-install/plugins/custom_dimensions.sql" >> /opt/matomo-install/install_plugins_db.sh \
;fi

RUN if [ -n "$MATOMO_PLUGIN_LOGINFILTERIP_VERSION" ]; then \
    curl -fsSL -o LoginFilterIp.zip \
        "https://github.com/AgID/wai-matomo-plugin-LoginFilterIp/archive/${MATOMO_PLUGIN_LOGINFILTERIP_VERSION}.zip" \
    && unzip LoginFilterIp.zip -d /opt/matomo/plugins \
    && mv /opt/matomo/plugins/wai-matomo-plugin-LoginFilterIp-${MATOMO_PLUGIN_LOGINFILTERIP_VERSION} /opt/matomo/plugins/LoginFilterIp \
    && rm -rf LoginFilterIp.zip \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "LoginFilterIp"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "LoginFilterIp"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_MORE_CONFIG@)/[LoginFilterIp]\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_MORE_CONFIG@)/allow_login_from[] = "localhost"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_MORE_CONFIG@)/allow_login_from[] = "@MATOMO_WAI_URL@"\n\1/g' /opt/matomo/config/config.ini.php \
;fi

RUN if [ -n "$MATOMO_PLUGIN_TRACKERHOSTNAME_VERSION" ]; then \
    curl -fsSL -o TrackerHostname.tar.gz \
        "https://github.com/AgID/wai-matomo-plugin-TrackerHostname/releases/download/${MATOMO_PLUGIN_TRACKERHOSTNAME_VERSION}/TrackerHostname-${MATOMO_PLUGIN_TRACKERHOSTNAME_VERSION}.tar.gz" \
    && tar -xzf TrackerHostname.tar.gz -C /opt/matomo/plugins \
    && rm -rf TrackerHostname.tar.gz \
    && sed -i -E -e 's/(@MATOMO_PLUGINS@)/Plugins[] = "TrackerHostname"\n\1/g' /opt/matomo/config/config.ini.php \
    && sed -i -E -e 's/(@MATOMO_PLUGINS_INSTALLED@)/PluginsInstalled[] = "TrackerHostname"\n\1/g' /opt/matomo/config/config.ini.php \
    && if [ -n "$MATOMO_INGESTION_HOSTNAME" ]; then \
        sed -i -E -e 's/(@MATOMO_MORE_CONFIG@)/[TrackerHostname]\n\1/g' /opt/matomo/config/config.ini.php \
        && sed -i -E -e 's/(@MATOMO_MORE_CONFIG@)/hostname = "${MATOMO_INGESTION_HOSTNAME}"\n\1/g' /opt/matomo/config/config.ini.php \
    ;fi \
;fi

ARG MATOMO_WAI_URL
RUN sed -i -e s/@MATOMO_WAI_URL@/${MATOMO_WAI_URL}/g /opt/matomo/config/config.ini.php && \
    sed -i -e s/@MATOMO_PLUGINS@//g /opt/matomo/config/config.ini.php && \
    sed -i -e s/@MATOMO_PLUGINS_INSTALLED@//g /opt/matomo/config/config.ini.php && \
    sed -i -e s/@MATOMO_MORE_CONFIG@//g /opt/matomo/config/config.ini.php

ARG DB_PASSWORD
ARG MATOMO_DB_READER
ARG MATOMO_DB_USER
ARG MATOMO_DB_PASSWORD
RUN sed -i -e s/@DB_PASSWORD@/${DB_PASSWORD}/g /opt/matomo-install/install_matomo_db.sh && \
    sed -i -e s/@DB_PASSWORD@/${DB_PASSWORD}/g /opt/matomo-install/install_plugins_db.sh && \
    sed -i -e s/@MATOMO_DB_READER@/${MATOMO_DB_READER}/g /opt/matomo/config/config.ini.php && \
    sed -i -e s/@MATOMO_DB_USER@/${MATOMO_DB_USER}/g /opt/matomo/config/config.ini.php && \
    sed -i -e s/@MATOMO_DB_PASSWORD@/${MATOMO_DB_PASSWORD}/g /opt/matomo/config/config.ini.php

ARG MATOMO_ROOT_USER
ARG MATOMO_ROOT_PASSWORD
ARG MATOMO_ROOT_APIKEY
RUN sed -i -e s/@MATOMO_ROOT_USER@/${MATOMO_ROOT_USER}/g /opt/matomo-install/install_matomo_db.sh && \
    sed -i -e s/@MATOMO_DB_USER@/${MATOMO_DB_USER}/g /opt/matomo-install/install_matomo_db.sh && \
    sed -i -e s/@MATOMO_DB_PASSWORD@/${MATOMO_DB_PASSWORD}/g /opt/matomo-install/install_matomo_db.sh && \
    sed -i -e s#@MATOMO_ROOT_PASSWORD@#${MATOMO_ROOT_PASSWORD}#g /opt/matomo-install/install_matomo_db.sh && \
    sed -i -e s/@MATOMO_ROOT_APIKEY@/${MATOMO_ROOT_APIKEY}/g /opt/matomo-install/install_matomo_db.sh
