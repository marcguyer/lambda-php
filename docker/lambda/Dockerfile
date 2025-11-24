# Local Lambda test environment
# Mimics AWS Lambda environment with our custom PHP runtime

FROM public.ecr.aws/lambda/provided:al2023

# Copy the built runtime layer to /opt (includes bootstrap, PHP binary, runtime classes)
COPY runtime/layer/ /opt/

# RIE expects bootstrap at /var/runtime/bootstrap, create symlink from layer's bootstrap
RUN mkdir -p /var/runtime && \
    ln -s /opt/bootstrap /var/runtime/bootstrap && \
    chmod +x /opt/bin/php

# Copy function code
COPY function /var/task

# Set default handler (used by our bootstrap)
ENV LAMBDA_HANDLER=handler.php

# Keep base image's ENTRYPOINT (RIE), provide dummy handler for entrypoint script
# The entrypoint will execute /var/runtime/bootstrap
CMD ["function.handler"]