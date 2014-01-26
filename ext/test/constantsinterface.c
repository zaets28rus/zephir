#include "header.h"


ZEPHIR_INIT_CLASS(Test_ConstantsInterface) {

	ZEPHIR_REGISTER_CLASS(Test, ConstantsInterface, test, constantsinterface, test_constantsinterface_method_entry, 0);

	zend_class_implements(test_constantsinterface_ce TSRMLS_CC, 1, test_testinterface_ce);

	return SUCCESS;

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant1) {


	RETURN_NULL();

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant2) {


	RETURN_BOOL(0);

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant3) {


	RETURN_BOOL(1);

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant4) {


	RETURN_LONG(10);

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant5) {


	RETURN_DOUBLE(10.25);

}

PHP_METHOD(Test_ConstantsInterface, testReadInterfaceConstant6) {


	RETURN_STRING("test", 1);

}

