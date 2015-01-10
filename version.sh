#!/bin/bash
# write current version to file

git describe --always --dirty | tee VERSION
