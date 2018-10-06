import React, { PureComponent, PropTypes } from 'react';
import classnames from 'classnames';
import FormBuilderLoader from 'containers/FormBuilderLoader/FormBuilderLoader';
import { getElementSchemaValue } from 'state/editor/getElementConfig';

class InlineEditForm extends PureComponent {
  render() {
    const { elementId, extraClass, onClick } = this.props;

    const classNames = classnames('element-editor-editform', extraClass);
    const schemaUrl = getElementSchemaValue('schemaUrl', elementId);

    const formProps = {
      formTag: 'div',
      schemaUrl,
      identifier: 'element',
    };

    return (
      <div className={classNames} onClick={onClick} role="presentation">
        <FormBuilderLoader {...formProps} />
      </div>
    );
  }
}

InlineEditForm.propTypes = {
  extraClass: PropTypes.oneOfType([PropTypes.string, PropTypes.object]),
  onClick: PropTypes.func,
  elementId: PropTypes.number,
};

export default InlineEditForm;
