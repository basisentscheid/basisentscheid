#!/bin/bash
# write current version to file

git describe --tags --always --dirty | tee VERSION
