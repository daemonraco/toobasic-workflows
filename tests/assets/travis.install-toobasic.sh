#
P_MODULE_DIR="$PWD";
#
cd ..

echo "Cloning TooBasic (using branch '${TRAVISCI_TOOBASIC_BRANCH}'):";
git clone https://github.com/daemonraco/toobasic.git toobasic --branch ${TRAVISCI_TOOBASIC_BRANCH}

cd toobasic
echo "Loading sub-repositories:";
git submodule init
git submodule update

cd modules
echo "Copying plugin:";
cp -frv "${P_MODULE_DIR}" toobasic-plugin;

echo "Downloading TooBasic Logger module:";
git clone https://github.com/daemonraco/toobasic-logger.git toobasic-logger;

cd ../..
